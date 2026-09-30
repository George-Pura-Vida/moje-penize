"""Build a deployment archive using an explicit allowlist, without server secrets."""
import hashlib
import json
from pathlib import Path
import zipfile

root = Path(__file__).resolve().parents[1]
files = ['install.php', 'index.html', 'robots.txt', 'api/health.php',
         'lib/bootstrap.php', 'lib/schema.php', 'assets/app.css', 'assets/app.js',
         'sql/mysql/001_schema.sql', 'sql/mysql/002_modules.sql', 'sql/mysql/003_dashboard.sql']
dist = root / 'dist'
dist.mkdir(exist_ok=True)
manifest = {name: hashlib.sha256((root / name).read_bytes()).hexdigest() for name in files}
with zipfile.ZipFile(dist / 'moje-penize.zip', 'w', zipfile.ZIP_DEFLATED) as archive:
    for name in files:
        archive.write(root / name, name)
    archive.writestr('release-manifest.json', json.dumps(manifest, indent=2) + '\n')
print(dist / 'moje-penize.zip')
