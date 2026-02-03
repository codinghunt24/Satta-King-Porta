#!/usr/bin/env python3
import re
import sys

input_file = 'attached_assets/digitalcash24_satta_(1)_1770097024396.sql'
output_file = 'posts_import.sql'

with open(input_file, 'r', encoding='utf-8', errors='ignore') as f:
    content = f.read()

pattern = r"INSERT INTO `posts` \(`id`, `title`, `slug`, `meta_description`, `meta_keywords`, `games_included`, `post_date`, `views`, `created_at`, `updated_at`\) VALUES\s*(.*?);"
matches = re.findall(pattern, content, re.DOTALL)

all_values = []
for match in matches:
    values_str = match.strip()
    row_pattern = r"\((\d+),\s*'((?:[^'\\]|\\.|'')*)',\s*'((?:[^'\\]|\\.|'')*)',\s*'((?:[^'\\]|\\.|'')*)',\s*'((?:[^'\\]|\\.|'')*)',\s*'((?:[^'\\]|\\.|'')*)',\s*'(\d{4}-\d{2}-\d{2})',\s*(\d+),\s*'(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})',\s*'(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})'\)"
    rows = re.findall(row_pattern, values_str)
    for row in rows:
        all_values.append(row)

print(f"Found {len(all_values)} posts")

with open(output_file, 'w', encoding='utf-8') as f:
    f.write("-- Posts import for PostgreSQL\n")
    f.write("-- Run: psql -h localhost -U sattauser -d sattaking -f posts_import.sql\n\n")
    
    for row in all_values:
        id_val, title, slug, meta_desc, meta_kw, games, post_date, views, created, updated = row
        
        title = title.replace("'", "''").replace("\\", "")
        slug = slug.replace("'", "''").replace("\\", "")
        meta_desc = meta_desc.replace("'", "''").replace("\\", "")
        meta_kw = meta_kw.replace("'", "''").replace("\\", "")
        games = games.replace("'", "''").replace("\\", "")
        
        sql = f"""INSERT INTO posts (id, title, slug, meta_description, meta_keywords, games_included, post_date, views, created_at, updated_at) 
VALUES ({id_val}, '{title}', '{slug}', '{meta_desc}', '{meta_kw}', '{games}', '{post_date}', {views}, '{created}', '{updated}')
ON CONFLICT (id) DO NOTHING;
"""
        f.write(sql)
    
    f.write(f"\n-- Reset sequence\nSELECT setval('posts_id_seq', (SELECT COALESCE(MAX(id), 1) FROM posts));\n")

print(f"Written to {output_file}")
