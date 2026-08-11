<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output method="html" encoding="UTF-8" omit-xml-declaration="yes"/>
    <xsl:template match="/">
        <html lang="zh-CN">
            <head>
                <meta charset="utf-8"/>
                <meta name="viewport" content="width=device-width, initial-scale=1"/>
                <title><xsl:value-of select="rss/channel/title"/> RSS</title>
                <style>
                    :root { color-scheme: light; font: 16px/1.6 system-ui, sans-serif; color: #18201d; background: #f3f1ed; }
                    body { max-width: 860px; margin: 0 auto; padding: 32px 20px 56px; }
                    header { border-bottom: 1px solid #dfe2dc; margin-bottom: 24px; padding-bottom: 18px; }
                    h1 { margin: 0 0 6px; font-size: 1.8rem; }
                    .description, time { color: #77807b; }
                    article { background: #fbfaf7; border: 1px solid #dfe2dc; border-radius: 12px; margin: 14px 0; padding: 18px 20px; }
                    h2 { margin: 0 0 5px; font-size: 1.15rem; }
                    a { color: #b9402d; }
                    p { margin: 8px 0 0; }
                </style>
            </head>
            <body>
                <header>
                    <h1><xsl:value-of select="rss/channel/title"/> RSS</h1>
                    <p class="description"><xsl:value-of select="rss/channel/description"/></p>
                </header>
                <main>
                    <xsl:for-each select="rss/channel/item">
                        <article>
                            <h2><a href="{link}"><xsl:value-of select="title"/></a></h2>
                            <time><xsl:value-of select="pubDate"/></time>
                            <p><xsl:value-of select="description"/></p>
                        </article>
                    </xsl:for-each>
                </main>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>
