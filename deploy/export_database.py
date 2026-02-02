#!/usr/bin/env python3
"""
Export database data to SQL file for production import
Run this on Replit to generate production database dump
"""

import os
import psycopg2

DATABASE_URL = os.environ.get('DATABASE_URL')

def export_data():
    conn = psycopg2.connect(DATABASE_URL)
    cursor = conn.cursor()
    
    tables = [
        'site_settings',
        'games', 
        'satta_results',
        'posts',
        'news_posts',
        'site_pages',
        'scrape_sources',
        'ad_placements',
        'url_redirects'
    ]
    
    with open('deploy/production_data.sql', 'w') as f:
        f.write("-- Satta King Production Data Export\n")
        f.write("-- Generated from Replit database\n\n")
        
        for table in tables:
            try:
                cursor.execute(f"SELECT * FROM {table}")
                rows = cursor.fetchall()
                
                if rows:
                    cursor.execute(f"SELECT column_name FROM information_schema.columns WHERE table_name = '{table}' ORDER BY ordinal_position")
                    columns = [col[0] for col in cursor.fetchall()]
                    
                    f.write(f"\n-- Table: {table}\n")
                    f.write(f"TRUNCATE TABLE {table} CASCADE;\n")
                    
                    for row in rows:
                        values = []
                        for val in row:
                            if val is None:
                                values.append('NULL')
                            elif isinstance(val, (int, float)):
                                values.append(str(val))
                            elif isinstance(val, bool):
                                values.append('TRUE' if val else 'FALSE')
                            else:
                                escaped = str(val).replace("'", "''")
                                values.append(f"'{escaped}'")
                        
                        cols_str = ', '.join(columns)
                        vals_str = ', '.join(values)
                        f.write(f"INSERT INTO {table} ({cols_str}) VALUES ({vals_str});\n")
                    
                    print(f"Exported {len(rows)} rows from {table}")
            except Exception as e:
                print(f"Error exporting {table}: {e}")
    
    cursor.close()
    conn.close()
    print("\nExport complete: deploy/production_data.sql")

if __name__ == '__main__':
    export_data()
