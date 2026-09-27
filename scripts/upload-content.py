#!/usr/bin/env python3
"""
Bulk upload game content to the API.

Usage:
    python3 upload-content.py --game kirako --file items.json
    python3 upload-content.py --game papagaj --file items.csv
    python3 upload-content.py --game erzelmek --url http://localhost:8000/api/content --file items.json
"""

import json
import csv
import sys
import argparse
import requests
from pathlib import Path


def load_json(file_path):
    """Load items from JSON file."""
    with open(file_path, 'r', encoding='utf-8') as f:
        data = json.load(f)

    if isinstance(data, list):
        return data
    elif isinstance(data, dict) and 'items' in data:
        return data['items']
    else:
        raise ValueError("JSON must be a list of items or {items: [...]}")


def load_csv(file_path):
    """Load items from CSV file.

    First row is headers. Remaining rows are items.
    Nested fields use dot notation: payload.name, payload.emoji, etc.
    """
    items = []

    with open(file_path, 'r', encoding='utf-8') as f:
        reader = csv.DictReader(f)

        for row in reader:
            item = {}
            payload = {}

            for key, value in row.items():
                if key.startswith('payload.'):
                    field = key[8:]  # Remove 'payload.' prefix
                    payload[field] = value
                elif key == 'level':
                    item['level'] = int(value) if value else 1
                else:
                    item[key] = value

            if payload:
                item['payload'] = payload

            items.append(item)

    return items


def upload_bulk(url, game, items):
    """Upload items to the API in bulk."""
    api_url = f"{url.rstrip('/')}/content/{game}/bulk"

    print(f"Uploading {len(items)} items to {api_url}...")

    try:
        response = requests.post(
            api_url,
            json={'items': items},
            headers={'Content-Type': 'application/json'},
            timeout=30
        )
        response.raise_for_status()

        result = response.json()
        print(f"✓ Imported: {result.get('imported', 0)}")

        if result.get('errors'):
            print(f"✗ Errors: {len(result['errors'])}")
            for error in result['errors'][:5]:
                print(f"  - Item {error.get('index')}: {error.get('errors')}")
            if len(result['errors']) > 5:
                print(f"  ... and {len(result['errors']) - 5} more")

        return result.get('imported', 0) > 0

    except requests.exceptions.RequestException as e:
        print(f"✗ Request failed: {e}")
        return False


def main():
    parser = argparse.ArgumentParser(
        description='Upload game content to Beszéd API',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog='''
Examples:
  python3 upload-content.py --game kirako --file items.json
  python3 upload-content.py --game papagaj --file items.csv --url http://localhost:8000/api
        '''
    )

    parser.add_argument('--game', required=True, help='Game name (e.g., kirako, papagaj)')
    parser.add_argument('--file', required=True, help='JSON or CSV file with items')
    parser.add_argument('--url', default='http://localhost:8000/api', help='API base URL')

    args = parser.parse_args()

    file_path = Path(args.file)

    if not file_path.exists():
        print(f"✗ File not found: {file_path}")
        sys.exit(1)

    # Load items based on file extension
    try:
        if file_path.suffix.lower() == '.json':
            items = load_json(file_path)
        elif file_path.suffix.lower() == '.csv':
            items = load_csv(file_path)
        else:
            print(f"✗ Unsupported file type: {file_path.suffix}")
            sys.exit(1)
    except Exception as e:
        print(f"✗ Error loading file: {e}")
        sys.exit(1)

    if not items:
        print("✗ No items found in file")
        sys.exit(1)

    print(f"Loaded {len(items)} items from {file_path}")

    # Upload to API
    success = upload_bulk(args.url, args.game, items)

    sys.exit(0 if success else 1)


if __name__ == '__main__':
    main()
