"""Integration tests in an isolated temporary copy; never use server config.php."""
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

source = Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory() as directory:
    root = Path(directory)
    for name in ('install.php', 'lib', 'api', 'sql'):
        src = source / name
        if src.is_dir():
            shutil.copytree(src, root / name)
        else:
            shutil.copy2(src, root / name)
    (root / 'config.php').write_text("<?php return ['db'=>['host'=>'127.0.0.1','port'=>3306,'name'=>'moje_penize_test','user'=>'root','pass'=>getenv('TEST_DB_PASSWORD')]];")

    def php(code):
        return subprocess.run(['php', '-r', "require 'lib/bootstrap.php'; " + code], cwd=root, check=True, capture_output=True, text=True)

    def install(apply=False):
        return subprocess.run(['php', 'install.php'] + (['--apply'] if apply else []), cwd=root, capture_output=True, text=True)

    def get(path):
        try:
            with urllib.request.urlopen(base + path) as response:
                return response.status, response.read().decode()
        except urllib.error.HTTPError as error:
            return error.code, error.read().decode()

    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    base = f'http://127.0.0.1:{port}/'
    server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(root)], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for _ in range(50):
            try:
                get('api/health.php')
                break
            except OSError:
                time.sleep(.1)
        assert get('install.php') == (403, 'Installer is locked.')
        assert get('api/health.php')[0] == 503
        assert install().returncode == 1
        assert not (root / '.installed').exists()
        # Simulate interrupted installation and an unreliable old marker.
        php("db()->exec(file_get_contents('sql/mysql/001_schema.sql'));")
        (root / '.installed').write_text('stale marker')
        assert install(True).returncode == 0
        php("db()->exec(\"INSERT INTO users(email,name,role) VALUES ('fixture@example.invalid','Fixture','ADMIN')\");")
        status, body = get('api/health.php')
        assert status == 200 and json.loads(body)['schema'] == 'ready'
        assert install(True).returncode == 0
        assert php("echo db()->query('SELECT COUNT(*) FROM users')->fetchColumn();").stdout == '1'
        # Simulate a missing module after an interrupted deployment, then recover.
        php("db()->exec('DROP VIEW v_care_dashboard');")
        assert get('api/health.php')[0] == 503
        assert install(True).returncode == 0
        assert get('api/health.php')[0] == 200
        (root / '.installed').unlink()
        assert get('install.php') == (403, 'Installer is locked.')
        print('PASS: fresh/partial installation, repeat run, data preservation, missing view recovery, HTTP lock and health')
    finally:
        server.terminate()
        server.wait(timeout=10)
