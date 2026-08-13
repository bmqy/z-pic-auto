# Z-Pic Auto

一个不依赖 Node.js、Composer 或 HTTPS 的 PHP 自动图集站。当前代码兼容 PHP 7.2.5，适合老式 ASP/PHP 虚拟主机；数据库优先使用主机提供的 MySQL，也支持 PDO SQLite。

## 能做什么

- 按配置每天采集 JSON、RSS 或 HTML 图集源。
- 自动下载图片到 `storage/images`，避免前台依赖外链。
- 按关键词规则自动归类，去重后生成图集详情页。
- 服务端渲染首页、分类页、搜索页和图集页。
- 自动输出规范链接、Open Graph、JSON-LD、RSS、`robots.txt` 和 `sitemap.xml`。
- 支持 CLI cron 和虚拟主机“网页定时任务”两种采集方式。

## 安装

1. 将整个目录上传到虚拟主机的网站根目录。
2. 复制 `config/local.example.php` 为 `config/local.php`。
3. 修改 `site_url`、`site_name`、`admin_token`、数据库连接和 `sources`。`site_url` 可使用 `http://`。
4. 在虚拟主机控制面板创建 MySQL 数据库，并将数据库名、用户名、密码填入配置。
5. 确认 `storage/` 和 `storage/images/` 可写。
6. 浏览器访问站点首页，应用会自动创建数据库表和默认分类。
7. 先访问 `/admin/?token=你的令牌` 手动运行一次采集，确认来源格式正确。

## 定时任务

采集入库前会使用 Google Translate 的公开 JSON 接口，把标题、描述、分类、图片 alt 和来源名称翻译为简体中文。纯中文内容会直接旁路；翻译服务请求失败时本次来源会失败，不会把未翻译的外文内容写入数据库。默认配置位于 `config/local.example.php` 的 `translation` 节点，可在 `config/local.php` 中覆盖接口地址、目标语言和超时时间。

优先使用主机面板的 Cron：

```text
php -q /home/你的账号/public_html/cron/collect.php
```

如果主机只支持网页定时任务：

```text
http://你的域名/index.php?route=task/collect&token=你的令牌
```

建议每天低峰期执行 1 次。不要高频抓取第三方站点，也不要采集没有授权的图片。

## GitHub Actions 定时采集

项目提供两个 Actions 采集工作流：`.github/workflows/collect-actions.yml` 默认每天北京时间 10:00 在 GitHub Runner 抓取并翻译内容，再通过 `index.php?route=task/import` 将 JSON 提交给生产站点；PHP 站点在本机连接 MySQL、下载图片并入库。`.github/workflows/collect.yml` 保留为直接调用站点 `index.php?route=task/collect` 的备用方式。

请在仓库 Settings → Secrets and variables → Actions 中配置：

- `SITE_URL`：站点根地址，例如 `https://example.com`。
- `ADMIN_TOKEN`：与生产 `config/local.php` 中的 `admin_token` 一致的令牌；站点环境变量和 Actions Secret 使用同一名称。

如果修改了定时时间，请按 UTC 填写 cron 表达式。工作流会在返回 HTTP 错误或任一来源返回 `[failed]` 时标记为失败。

`collect-actions.yml` 与线上站点共用 `config/loader.php` 加载配置：以 `config/local.example.php` 为默认来源，叠加 `config/local.php` 和环境变量；Actions 运行时关闭数据库必需校验，但会使用相同的 `PEXELS_API_KEY` 激活规则。翻译服务由 Actions 端调用，生产服务器不需要能够访问翻译服务。导入接口使用 `X-Admin-Token` 请求头认证，生产服务器仍只负责本机数据库写入。

Actions 日志会输出 `[actions-source-config]` 来源配置行、`[actions-source-result]` 来源执行结果行和 `[actions-source-summary]` 汇总行。结果行包含来源状态（`disabled`、`failed`、`empty`、`skipped` 或 `success`）、抓取数量、导出数量、跳过数量和错误信息，可据此与线上运行记录核对实际抓取源及执行结果。

## GitHub Actions FTP 发布

项目提供 `.github/workflows/deploy-ftp.yml`：推送到 `main` 分支或手动运行工作流时，会将站点文件同步到 FTP。工作流会保留服务器上的 `storage/` 和生产配置，不会上传 `.env`、本地数据库、测试文件或 Docker 配置。

请在 GitHub 仓库的 `Settings → Secrets and variables → Actions` 中配置以下 Secrets：

- `FTP_HOST`：FTP 主机名或 IP。
- `FTP_USERNAME`：FTP 用户名。
- `FTP_PASSWORD`：FTP 密码。
- `FTP_SERVER_DIR`：站点远程目录，例如 `/Web/`。

当远端缺少或存在空的 `config/local.php` 时，部署工作流会自动使用以下 Secrets 初始化生产配置：

- `SITE_URL`、`ADMIN_TOKEN`
- `DB_HOST`、`DB_PORT`、`DB_NAME`、`DB_USERNAME`、`DB_PASSWORD`
- `VERIFY_SSL`：可选，默认为 `1`；仅用于站点服务器请求外部 HTTPS 时的证书校验。

工作流当前使用普通 FTP 21 端口；如果主机要求 FTPS，需要同步修改工作流中的 `protocol` 和 `port`。

## Docker 本地调试

安装并启动 Docker Desktop 后，在项目根目录执行：

```bash
docker compose up --build -d
```

访问 `http://localhost:18080`。本地镜像会自动使用 `config/local.docker.php` 和内置测试源，首次采集可访问：

```text
http://localhost:18080/index.php?route=task/collect&token=local-debug-token
```

本地调试完成后，虚拟机部署不要使用 Docker 测试配置；请复制 `config/local.example.php` 为 `config/local.php`，填入真实域名、令牌和授权来源。

本项目已提供 PHP 7.2.5 兼容性容器，可在本机执行：

```bash
docker compose -f docker-compose.php72.yml up --build -d
```

访问 `http://localhost:18072`。如果 PHP 7.2.5 基础镜像已从 Docker 仓库下线，则直接在目标虚拟主机执行 `php -l` 和首页访问检查即可。

如果虚拟机使用 Docker，生产启动命令为：

```bash
cp config/local.example.php config/local.php
# 编辑 config/local.php
docker compose -f docker-compose.prod.yml up --build -d
```

生产 Compose 会把 `config/local.php` 和 `storage/` 挂载到容器中，数据库和图片不会随容器重建丢失。

## 数据源格式

### JSON

返回数组或 `{ "items": [...] }`，每项示例：

```json
{
  "title": "春日山野",
  "description": "一组授权风景图片",
  "category": "风景",
  "source_url": "https://source.example/gallery/1",
  "images": [
    {"url": "https://source.example/images/1.jpg", "alt": "山野"}
  ]
}
```

### HTML

在 `selectors` 中填写 XPath。默认选择器要求列表项有 `gallery` 类，内部包含标题、描述、分类和 `img` 标签。图片支持 `src`、`data-src`、`data-original`。

## 说明

“自动收录”只能通过生成可抓取的高质量页面、站点地图和 RSS 来辅助搜索引擎发现，不能保证搜索引擎收录或流量增长。图片的版权、来源许可、删除请求和隐私合规需要由站长负责。

## Pexels API 来源

项目支持通过 Pexels API 的规范接口抓取图片。先在服务器环境设置 `PEXELS_API_KEY`，然后在 `config/local.php` 的 `sources` 中启用模板里的来源：

```php
[
    'name' => 'Pexels 精选图片',
    'type' => 'pexels',
    'url' => 'https://api.pexels.com/v1/curated',
    'enabled' => true,
    'per_page' => 1,
    'max_items' => 1,
    'max_images' => 1,
    'image_size' => 'large',
],
```

按关键词搜索时，将 `url` 改为 `https://api.pexels.com/v1/search`，并增加 `'query' => 'nature'`。采集结果会保留 Pexels 照片页来源链接，并将摄影师署名写入图集描述。请遵守 [Pexels API Guidelines](https://www.pexels.com/api/documentation/) 的回链、署名和请求频率要求。

## Bangumi API 来源

项目支持通过 Bangumi v0 API 抓取动画条目的公开信息和封面。默认模板和 FTP 发布工作流会启用该来源；如需关闭，请在 `config/local.php` 中将对应来源设为 `enabled => false`：

```php
[
    'name' => 'Bangumi 动画条目',
    'type' => 'bangumi',
    'url' => 'https://api.bgm.tv/v0/subjects',
    'enabled' => true,
    'params' => [
        'type' => 2,
        'sort' => 'rank',
        'limit' => 1,
        'offset' => 0,
    ],
    'category' => '二次元',
    'max_items' => 1,
    'max_images' => 1,
],
```

`type=2` 表示动画，采集结果会优先使用中文标题，并归入“二次元”分类；请按 [Bangumi API 文档](https://bangumi.github.io/api/) 的 User-Agent、请求频率和内容使用要求配置与使用。
