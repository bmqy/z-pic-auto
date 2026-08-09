CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(180) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name),
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS galleries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    source_name VARCHAR(180) NOT NULL,
    source_url VARCHAR(2048) NOT NULL,
    cover_path VARCHAR(2048) NOT NULL,
    fingerprint CHAR(40) NOT NULL,
    published TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_galleries_slug (slug),
    UNIQUE KEY uq_galleries_fingerprint (fingerprint),
    KEY idx_galleries_created_at (created_at),
    KEY idx_galleries_category_id (category_id),
    CONSTRAINT fk_galleries_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS images (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    gallery_id INT UNSIGNED NOT NULL,
    source_url VARCHAR(2048) NOT NULL,
    local_path VARCHAR(2048) NOT NULL,
    alt_text VARCHAR(500) NOT NULL,
    position INT NOT NULL DEFAULT 0,
    width INT NOT NULL DEFAULT 0,
    height INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_images_gallery_source (gallery_id, source_url(191)),
    KEY idx_images_gallery_id (gallery_id, position),
    CONSTRAINT fk_images_gallery FOREIGN KEY (gallery_id) REFERENCES galleries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS collection_runs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_name VARCHAR(180) NOT NULL,
    status VARCHAR(30) NOT NULL,
    added_count INT NOT NULL DEFAULT 0,
    message TEXT NOT NULL,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_collection_runs_id (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
