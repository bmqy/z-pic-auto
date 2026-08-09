<?php
declare(strict_types=1);

final class Repository
{
    private $db;
    private $config;

    public function __construct(PDO $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function ensureSchema(): void
    {
        $database = (array) ($this->config['database'] ?? []);
        $driver = strtolower((string) ($database['driver'] ?? 'sqlite'));
        $schemaFile = $driver === 'mysql' ? __DIR__ . '/../schema_mysql.sql' : __DIR__ . '/../schema.sql';
        $schema = file_get_contents($schemaFile);
        $statements = preg_split('/;\s*(?:\r?\n|$)/', (string) $schema);
        foreach ($statements as $statement) {
            if (trim($statement) !== '') {
                $this->db->exec($statement);
            }
        }
        foreach (array_keys((array) $this->config['category_rules']) as $category) {
            $this->ensureCategory($category);
        }
    }

    public function ensureCategory(string $name): int
    {
        $name = trim($name) !== '' ? trim($name) : '未分类';
        $stmt = $this->db->prepare('SELECT id FROM categories WHERE name = ? OR slug = ? LIMIT 1');
        $stmt->execute([$name, slugify($name)]);
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            return (int) $existing;
        }
        $stmt = $this->db->prepare('INSERT INTO categories(name, slug, created_at) VALUES(?, ?, ?)');
        $stmt->execute([$name, slugify($name), now_string()]);
        return (int) $this->db->lastInsertId();
    }

    public function categoryByName(string $name): array
    {
        $stmt = $this->db->prepare('SELECT * FROM categories WHERE name = ? OR slug = ? LIMIT 1');
        $stmt->execute([$name, slugify($name)]);
        return $stmt->fetch() ?: [];
    }

    public function galleryExistsByFingerprint(string $fingerprint): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM galleries WHERE fingerprint = ? LIMIT 1');
        $stmt->execute([$fingerprint]);
        return (bool) $stmt->fetchColumn();
    }

    public function galleryExistsByIdentity(string $fingerprint, string $sourceUrl): bool
    {
        if (trim($sourceUrl) === '') {
            return $this->galleryExistsByFingerprint($fingerprint);
        }
        $stmt = $this->db->prepare('SELECT 1 FROM galleries WHERE fingerprint = ? OR source_url = ? LIMIT 1');
        $stmt->execute([$fingerprint, $sourceUrl]);
        return (bool) $stmt->fetchColumn();
    }

    public function slugExists(string $slug): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM galleries WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        return (bool) $stmt->fetchColumn();
    }

    public function createGallery(array $item, array $images, string $sourceName): int
    {
        $categoryId = $this->ensureCategory((string) $item['category']);
        $slug = unique_slug((string) $item['title'], function ($value) {
            return $this->slugExists($value);
        });
        $now = now_string();
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('INSERT INTO galleries(title, slug, description, category_id, source_name, source_url, cover_path, fingerprint, created_at, updated_at) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $coverPath = (string) (($images[0]['local_path'] ?? '') !== '' ? $images[0]['local_path'] : ($images[0]['source_url'] ?? ''));
            $stmt->execute([
                $item['title'], $slug, $item['description'], $categoryId, $sourceName,
                $item['source_url'], $coverPath, $item['fingerprint'], $now, $now,
            ]);
            $galleryId = (int) $this->db->lastInsertId();
            $imageStmt = $this->db->prepare('INSERT INTO images(gallery_id, source_url, local_path, alt_text, position, width, height, created_at) VALUES(?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($images as $position => $image) {
                $imageStmt->execute([
                    $galleryId, $image['source_url'], $image['local_path'], $image['alt_text'],
                    $position, $image['width'], $image['height'], $now,
                ]);
            }
            $this->db->commit();
            return $galleryId;
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    public function recentGalleries(int $limit, int $offset = 0, ?int $categoryId = null): array
    {
        $sql = 'SELECT g.*, c.name AS category_name, c.slug AS category_slug FROM galleries g JOIN categories c ON c.id = g.category_id WHERE g.published = 1';
        $params = [];
        if ($categoryId !== null) {
            $sql .= ' AND g.category_id = ?';
            $params[] = $categoryId;
        }
        $sql .= ' ORDER BY g.created_at DESC, g.id DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
        $stmt = $this->db->prepare($sql);
        foreach ($params as $index => $param) {
            $stmt->bindValue($index + 1, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countGalleries(?int $categoryId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM galleries WHERE published = 1';
        $params = [];
        if ($categoryId !== null) {
            $sql .= ' AND category_id = ?';
            $params[] = $categoryId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function findGallery(string $slug): array
    {
        $stmt = $this->db->prepare('SELECT g.*, c.name AS category_name, c.slug AS category_slug FROM galleries g JOIN categories c ON c.id = g.category_id WHERE g.slug = ? AND g.published = 1 LIMIT 1');
        $stmt->execute([$slug]);
        $gallery = $stmt->fetch() ?: [];
        if ($gallery) {
            $imageStmt = $this->db->prepare('SELECT * FROM images WHERE gallery_id = ? ORDER BY position ASC, id ASC');
            $imageStmt->execute([(int) $gallery['id']]);
            $gallery['images'] = $imageStmt->fetchAll();
        }
        return $gallery;
    }

    public function categories(): array
    {
        return $this->db->query('SELECT c.*, COUNT(g.id) AS gallery_count FROM categories c LEFT JOIN galleries g ON g.category_id = c.id AND g.published = 1 GROUP BY c.id ORDER BY gallery_count DESC, c.name ASC')->fetchAll();
    }

    public function search(string $query, int $limit): array
    {
        $stmt = $this->db->prepare('SELECT g.*, c.name AS category_name, c.slug AS category_slug FROM galleries g JOIN categories c ON c.id = g.category_id WHERE g.published = 1 AND (g.title LIKE ? OR g.description LIKE ?) ORDER BY g.created_at DESC LIMIT ?');
        $like = '%' . $query . '%';
        $stmt->bindValue(1, $like, PDO::PARAM_STR);
        $stmt->bindValue(2, $like, PDO::PARAM_STR);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function allPublishedForSitemap(): array
    {
        return $this->db->query('SELECT slug, updated_at FROM galleries WHERE published = 1 ORDER BY updated_at DESC')->fetchAll();
    }

    public function recordRun(string $sourceName, string $status, int $added, string $message, string $started, string $finished): void
    {
        $stmt = $this->db->prepare('INSERT INTO collection_runs(source_name, status, added_count, message, started_at, finished_at) VALUES(?, ?, ?, ?, ?, ?)');
        $stmt->execute([$sourceName, $status, $added, $message, $started, $finished]);
    }

    public function recentRuns(int $limit = 20): array
    {
        $stmt = $this->db->prepare('SELECT * FROM collection_runs ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
