# Manager Monitor
```bash
backdoor-manager/
├── app/
│   ├── Filament/
│   │   ├── Resources/
│   │   │   ├── AgentResource.php
│   │   │   ├── AgentResource/
│   │   │   │   ├── Pages/
│   │   │   │   │   ├── ListAgents.php
│   │   │   │   │   └── ViewAgent.php
│   │   │   │   └── RelationManagers/
│   │   │   │       └── AlertsRelationManager.php
│   │   │   └── AlertResource.php
│   │   └── Pages/
│   │       └── HostsOverview.php
│   └── Models/
│       ├── Agent.php
│       └── Alert.php
├── resources/
│   └── views/
│       └── filament/
│           └── pages/
│               └── hosts-overview.blade.php   ← manual dibuat
└── database/
    └── migrations/
        ├── ..._create_agents_table.php
        └── ..._create_alerts_table.php
```

# backdoor-agent
backdoor agent

# Struktur Folder di VPS/Host
```bash
/opt/backdoor-agent/          ← folder utama (kamu pilih sendiri, misalnya /opt/backdoor-agent)
├── agent.py                  ← file utama agent (copy kode yang saya berikan)
├── config.yaml               ← file konfigurasi multi-app (buat manual)
├── keys/                     ← OTOMATIS dibuat oleh agent.py saat pertama jalan
│   ├── payment/              ← subfolder per app, OTOMATIS
│   │   ├── private_key.pem
│   │   └── public_key.pem
│   ├── dashboard/
│   └── api/
└── rules/                    ← BUAT MANUAL (untuk taruh file YARA rules)
    ├── php_webshells.yar
    ├── generic_webshells.yar
    └── (file .yar lain yang kamu tambah)
```
# config.yaml
```yaml
manager:
  url: "https://manager.example.com"

apps:
  payment:
    name: "Payment Gateway"
    directories:
      - /var/www/payment.example.com/html
      - /var/www/payment.example.com/uploads

  dashboard:
    name: "Admin Dashboard"
    directories:
      - /var/www/dashboard.example.com/html

  api:
    name: "API Service"
    directories:
      - /home/api/public
```
# Kode agent.py
```python
import os
import hashlib
import time
import yaml
import requests
import yara
import socket
import platform
import subprocess
import glob
from datetime import datetime
from watchdog.observers import Observer
from watchdog.events import FileSystemEventHandler
from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.primitives.asymmetric import rsa

# ================= CONFIG =================
CONFIG_FILE = "config.yaml"

if not os.path.exists(CONFIG_FILE):
    print(f"[!] File {CONFIG_FILE} tidak ditemukan!")
    exit(1)

with open(CONFIG_FILE, "r") as f:
    config = yaml.safe_load(f)

MANAGER_URL = config['manager']['url'].rstrip("/")
apps_config = config['apps']

HEADERS = {"Content-Type": "application/json"}

# ================= KEY MANAGEMENT =================
def get_or_generate_key(app_slug):
    key_dir = f"keys/{app_slug}"
    os.makedirs(key_dir, exist_ok=True)
    
    private_path = f"{key_dir}/private_key.pem"
    public_path = f"{key_dir}/public_key.pem"
    
    if os.path.exists(private_path) and os.path.exists(public_path):
        with open(private_path, "rb") as f:
            private_key = serialization.load_pem_private_key(f.read(), password=None)
        with open(public_path, "r") as f:
            public_key_pem = f.read()
        return private_key, public_key_pem
    
    print(f"[*] Generating new RSA key pair for app: {app_slug}")
    private_key = rsa.generate_private_key(public_exponent=65537, key_size=2048)
    
    # Save private key
    with open(private_path, "wb") as f:
        f.write(private_key.private_bytes(
            encoding=serialization.Encoding.PEM,
            format=serialization.PrivateFormat.PKCS8,
            encryption_algorithm=serialization.NoEncryption()
        ))
    os.chmod(private_path, 0o600)
    
    # Save public key
    public_key = private_key.public_key()
    public_pem = public_key.public_bytes(
        encoding=serialization.Encoding.PEM,
        format=serialization.PublicFormat.SubjectPublicKeyInfo
    )
    with open(public_path, "wb") as f:
        f.write(public_pem)
    
    return private_key, public_pem.decode('utf-8')

# ================= FIND PROJECT ROOT =================
def find_project_root(start_path):
    current = os.path.abspath(start_path)
    while current != '/' and current != '':
        # Laravel
        if os.path.exists(os.path.join(current, 'artisan')) or os.path.exists(os.path.join(current, 'bootstrap/app.php')):
            return current
        # WordPress
        if os.path.exists(os.path.join(current, 'wp-config.php')) or os.path.exists(os.path.join(current, 'wp-settings.php')):
            return current
        # CodeIgniter
        if os.path.exists(os.path.join(current, 'system/core/CodeIgniter.php')) or os.path.exists(os.path.join(current, 'app/Config/App.php')):
            return current
        # Joomla
        if os.path.exists(os.path.join(current, 'configuration.php')) and os.path.exists(os.path.join(current, 'administrator')):
            return current
        # composer.json untuk Laravel/Symfony/CodeIgniter
        composer_path = os.path.join(current, 'composer.json')
        if os.path.exists(composer_path):
            content = subprocess.getoutput(f"cat {composer_path} || true").lower()
            if "laravel/framework" in content or "codeigniter4" in content:
                return current
        
        # Node.js
        if os.path.exists(os.path.join(current, 'package.json')):
            return current
        
        # Python
        if any(os.path.exists(os.path.join(current, f)) for f in ['requirements.txt', 'Pipfile', 'pyproject.toml', 'poetry.lock']):
            return current
        
        current = os.path.dirname(current)
    
    return start_path

# ================= TECH STACK DETECTION =================
def detect_tech_stack(directories):
    tech = {
        "os": platform.system() + " " + platform.release(),
        "web_server": "Unknown",
        "database": "Not detected",
        "language": [],
        "framework": [],
        "container": "Docker" if os.path.exists("/.dockerenv") else "Bare Metal",
        "package_manager": [],
    }

    processes = subprocess.getoutput("ps aux")
    if "nginx" in processes:
        tech["web_server"] = "Nginx"
    elif "apache" in processes or "httpd" in processes:
        tech["web_server"] = "Apache"
    elif "caddy" in processes:
        tech["web_server"] = "Caddy"

    if "mysqld" in processes:
        tech["database"] = "MySQL/MariaDB"
    if "postgres" in processes:
        tech["database"] = "PostgreSQL"

    # Cari root project
    root_path = None
    for dir_path in directories:
        if os.path.exists(dir_path):
            candidate = find_project_root(dir_path)
            if candidate != dir_path:
                root_path = candidate
                break
    if not root_path:
        root_path = directories[0] if directories else "."

    if not os.path.exists(root_path):
        return tech

    files = os.listdir(root_path) if os.path.isdir(root_path) else []

    # PHP detection
    has_php = any(f.endswith('.php') for f in files) or "composer.json" in files or "index.php" in files
    if has_php:
        tech["language"].append("PHP")
        tech["package_manager"].append("Composer" if "composer.json" in files else "Native")

    # Framework detection
    if os.path.exists(os.path.join(root_path, 'artisan')) or os.path.exists(os.path.join(root_path, 'bootstrap/app.php')):
        tech["framework"].append("Laravel")

    if os.path.exists(os.path.join(root_path, 'wp-config.php')) or os.path.exists(os.path.join(root_path, 'wp-settings.php')):
        tech["framework"].append("WordPress")

    if os.path.exists(os.path.join(root_path, 'configuration.php')) and os.path.exists(os.path.join(root_path, 'administrator')):
        tech["framework"].append("Joomla")

    if os.path.exists(os.path.join(root_path, 'system/core/CodeIgniter.php')) or os.path.exists(os.path.join(root_path, 'app/Config/App.php')):
        tech["framework"].append("CodeIgniter")

    # PHP Native kalau tidak ada framework besar
    if has_php and not tech["framework"]:
        tech["framework"].append("PHP Native")

    # Node.js
    if "package.json" in files:
        tech["language"].append("Node.js")
        tech["package_manager"].append("npm/yarn/pnpm")
        pkg = subprocess.getoutput(f"cat {root_path}/package.json || true").lower()
        if "next" in pkg:
            tech["framework"].append("Next.js")
        if "nuxt" in pkg:
            tech["framework"].append("Nuxt.js")
        if "react" in pkg:
            tech["framework"].append("React")
        if "express" in pkg:
            tech["framework"].append("Express")
        if "nest" in pkg:
            tech["framework"].append("NestJS")

    # Python
    if any(f in files for f in ["requirements.txt", "Pipfile", "pyproject.toml", "poetry.lock"]):
        tech["language"].append("Python")
        tech["package_manager"].append("pip/poetry")
        output = subprocess.getoutput(f"grep -r -i -E 'django|fastapi|flask|starlette' {root_path} || true").lower()
        if "django" in output:
            tech["framework"].append("Django")
        if "fastapi" in output:
            tech["framework"].append("FastAPI")
        if "flask" in output:
            tech["framework"].append("Flask")

    # Deduplicate
    tech["language"] = list(set(tech["language"]))
    tech["framework"] = list(set(tech["framework"]))
    tech["package_manager"] = list(set(tech["package_manager"]))

    return tech

# ================= YARA =================
compiled_rules = None

def load_yara_rules():
    global compiled_rules
    rule_files = glob.glob("rules/*.yar")
    if not rule_files:
        print("[!] Tidak ada file YARA di folder rules/")
        return
    try:
        compiled_rules = yara.compile(filepaths={os.path.basename(f): f for f in rule_files})
        print(f"[+] Loaded {len(rule_files)} YARA rules")
    except Exception as e:
        print(f"[!] YARA compile error: {e}")

load_yara_rules()

def scan_with_yara(filepath):
    if compiled_rules is None:
        return []
    try:
        matches = compiled_rules.match(filepath)
        return [m.rule for m in matches]
    except:
        return []

# ================= SCANNING =================
file_hashes = {}

def scan_app(app_slug, app_cfg):
    alerts = []
    directories = app_cfg['directories']

    for dir_path in directories:
        if not os.path.exists(dir_path):
            continue

        for root, _, files in os.walk(dir_path):
            for file in files:
                if file.startswith('.'):
                    continue
                filepath = os.path.join(root, file)
                try:
                    current_hash = hashlib.sha256(open(filepath, "rb").read()).hexdigest()
                except:
                    continue

                key = f"{app_slug}:{filepath}"
                if key in file_hashes:
                    if file_hashes[key] != current_hash:
                        alerts.append({
                            "type": "file_modified",
                            "file": filepath,
                            "old_hash": file_hashes[key],
                            "new_hash": current_hash,
                        })
                else:
                    alerts.append({
                        "type": "file_created",
                        "file": filepath,
                        "hash": current_hash,
                    })
                file_hashes[key] = current_hash

                # YARA scan
                if file.endswith(('.php', '.js', '.py', '.jsp', '.asp', '.aspx')):
                    matched = scan_with_yara(filepath)
                    if matched:
                        alerts.append({
                            "type": "yara_webshell_match",
                            "file": filepath,
                            "hash": current_hash,
                            "matched_rules": matched,
                        })

    return alerts

# ================= SEND REPORT =================
def send_report(app_id, alerts):
    if not alerts:
        return

    payload = {
        "app_id": app_id,
        "alerts": alerts,
    }

    try:
        response = requests.post(f"{MANAGER_URL}/api/report", json=payload, headers=HEADERS, timeout=15)
        if response.status_code == 200:
            print(f"[+] Sent {len(alerts)} alerts for {app_id}")
        else:
            print(f"[!] Report failed: {response.status_code} {response.text}")
    except Exception as e:
        print(f"[!] Send error: {e}")

# ================= REGISTRATION =================
def register_app(app_id, app_cfg, public_key_pem):
    hostname = socket.gethostname()

    # Ambil IP lokal server (akurat)
    def get_local_ip():
        try:
            s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            s.connect(("8.8.8.8", 80))
            local_ip = s.getsockname()[0]
            s.close()
            return local_ip
        except:
            return "127.0.0.1"

    ip_address = get_local_ip()

    tech_stack = detect_tech_stack(app_cfg['directories'])

    payload = {
        "app_id": app_id,
        "app_name": app_cfg['name'],
        "hostname": hostname,
        "ip_address": ip_address,
        "public_key": public_key_pem,
        "tech_stack": tech_stack,
    }

    try:
        response = requests.post(f"{MANAGER_URL}/api/register", json=payload, headers=HEADERS, timeout=15)
        if response.status_code in [200, 201]:
            print(f"[+] Registered: {app_cfg['name']} ({app_id}) - IP: {ip_address}")
        else:
            print(f"[!] Registration failed: {response.status_code} {response.text}")
    except Exception as e:
        print(f"[!] Registration error: {e}")

# ================= MAIN =================
def main():
    print("[*] Starting Backdoor Detector Agent")

    hostname = socket.gethostname()

    for app_slug, app_cfg in apps_config.items():
        app_id = f"{hostname}-{app_slug}"
        print(f"[*] Initializing: {app_cfg['name']} ({app_id})")

        _, public_key_pem = get_or_generate_key(app_slug)
        register_app(app_id, app_cfg, public_key_pem)

        alerts = scan_app(app_slug, app_cfg)
        send_report(app_id, alerts)

    # Realtime monitoring
    class Handler(FileSystemEventHandler):
        def on_modified(self, event):
            if event.is_directory:
                return
            for app_slug, app_cfg in apps_config.items():
                for dir_path in app_cfg['directories']:
                    if event.src_path.startswith(dir_path):
                        alerts = scan_app(app_slug, app_cfg)
                        send_report(f"{hostname}-{app_slug}", alerts)

        def on_created(self, event):
            self.on_modified(event)

    observer = Observer()
    for app_cfg in apps_config.values():
        for dir_path in app_cfg['directories']:
            if os.path.exists(dir_path):
                observer.schedule(Handler(), dir_path, recursive=True)

    observer.start()

    try:
        while True:
            time.sleep(300)  # periodik scan setiap 5 menit
            for app_slug, app_cfg in apps_config.items():
                alerts = scan_app(app_slug, app_cfg)
                send_report(f"{hostname}-{app_slug}", alerts)
    except KeyboardInterrupt:
        print("\n[*] Stopping...")
        observer.stop()
    observer.join()

if __name__ == "__main__":
    main()
```
# Buat Systemd Service File di agent backdoor 
```bash
sudo nano /etc/systemd/system/backdoor-agent.service
```
isinya : 
```bash
[Unit]
Description=Backdoor Detector Agent (Multi-App)
After=network.target
Wants=network-online.target

[Service]
Type=simple
User=root                          # bisa diganti user biasa kalau mau lebih aman
Group=root
WorkingDirectory=/opt/backdoor-agent
ExecStart=/usr/bin/python3 /opt/backdoor-agent/agent.py
Restart=always                     # otomatis restart kalau crash
RestartSec=10                      # delay 10 detik sebelum restart
StandardOutput=append:/var/log/backdoor-agent.log
StandardError=append:/var/log/backdoor-agent.log
Environment=PYTHONUNBUFFERED=1     # biar log real-time

[Install]
WantedBy=multi-user.target
```
# Aktifkan dan Jalankan Service
```bash
# Reload systemd biar baca file baru
sudo systemctl daemon-reload

# Aktifkan supaya start otomatis saat boot
sudo systemctl enable backdoor-agent

# Jalankan sekarang
sudo systemctl start backdoor-agent

# Cek status
sudo systemctl status backdoor-agent
```
# Output yang keluar akan seperti ini:
```bash
● backdoor-agent.service - Backdoor Detector Agent (Multi-App)
     Loaded: loaded (/etc/systemd/system/backdoor-agent.service; enabled)
     Active: active (running) since Wed 2025-12-24 15:30:45 WIB; 10s ago
   Main PID: 12345 (python3)
      Tasks: 5
     Memory: 45.2M
        CPU: 1.234s
```
# Cek Log Agent
```bash
# Log real-time
tail -f /var/log/backdoor-agent.log

# Atau pakai journalctl
journalctl -u backdoor-agent -f
```
# Kamu akan lihat output seperti:
```bash
[*] Starting Backdoor Detector Agent (Multi-App Mode)
[*] Initializing app: Payment Gateway (vps-prod-01-payment)
[+] Generating new RSA key pair for app: payment
[+] Registered app: Payment Gateway (vps-prod-01-payment)
[+] Loaded 2 YARA rule files
```
# Command Berguna Lainnya
```bash
# Stop service
sudo systemctl stop backdoor-agent

# Restart (misalnya setelah edit config.yaml)
sudo systemctl restart backdoor-agent

# Lihat log lengkap
journalctl -u backdoor-agent --since "1 hour ago"
```
# Keamanan Tambahan (Opsional tapi Direkomendasikan)
```bash
# Buat user khusus
sudo useradd -r -s /bin/false backdooragent

# Ganti di service file
User=backdooragent
Group=backdooragent

# Beri ownership folder
sudo chown -R backdooragent:backdooragent /opt/backdoor-agent
```
