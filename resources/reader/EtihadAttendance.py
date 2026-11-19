"""Etihad attendance reader.

Reads the ZKTeco attendance machine over the office network every few minutes and sends its people and punches
to the Etihad website.

Double-click the downloaded file once: it installs itself in the user's AppData folder, starts with Windows and
keeps running in the background with no window. The machine's serial number, secret key and IP address come in
the downloaded file's name (EtihadAttendance_<serial>_<key>_<ip>.exe), so nothing has to be typed.
"""
import configparser
import ctypes
import datetime
import json
import os
import re
import shutil
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import winreg

from zk import ZK

WEBSITE = 'https://etihad-publisher.nexverse.co.uk'
APP_NAME = 'EtihadAttendance'
INSTALL_DIR = os.path.join(os.environ.get('LOCALAPPDATA', os.path.expanduser('~')), APP_NAME)
INSTALLED_EXE = os.path.join(INSTALL_DIR, APP_NAME + '.exe')
INI = os.path.join(INSTALL_DIR, 'etihad-attendance.ini')
LOG = os.path.join(INSTALL_DIR, 'etihad-attendance.log')
RUN_KEY = r'Software\Microsoft\Windows\CurrentVersion\Run'
DAYS_BACK = 45
EVERY_MINUTES = 5


def message(text: str, title: str = 'Etihad attendance reader', error: bool = False) -> None:
    ctypes.windll.user32.MessageBoxW(None, text, title, 0x10 if error else 0x40)


def log(text: str) -> None:
    os.makedirs(INSTALL_DIR, exist_ok=True)
    line = f'[{datetime.datetime.now():%d %b %Y %I:%M:%S %p}] {text}'
    try:
        # Keep the log small: the last ~2000 lines.
        lines = open(LOG, encoding='utf8').read().splitlines()[-2000:] if os.path.exists(LOG) else []
        with open(LOG, 'w', encoding='utf8') as f:
            f.write('\n'.join(lines + [line]) + '\n')
    except OSError:
        pass


def settings_from_name(path: str) -> dict | None:
    """EtihadAttendance_<serial>_<key>_<ip>.exe, possibly with " (1)" added by the browser."""
    m = re.match(r'^EtihadAttendance_([A-Za-z0-9-]+)_([A-Za-z0-9]{20,64})_(\d{1,3}(?:\.\d{1,3}){3})', os.path.basename(path))
    if not m:
        return None
    return {'website': WEBSITE, 'serial': m.group(1), 'key': m.group(2), 'machine_ip': m.group(3)}


def load_settings() -> dict | None:
    if not os.path.exists(INI):
        return None
    parser = configparser.ConfigParser()
    parser.read(INI, encoding='utf8')
    s = parser['etihad']
    return {'website': s.get('website', WEBSITE).rstrip('/'), 'serial': s.get('serial'), 'key': s.get('key'), 'machine_ip': s.get('machine_ip')}


def save_settings(cfg: dict) -> None:
    os.makedirs(INSTALL_DIR, exist_ok=True)
    parser = configparser.ConfigParser()
    parser['etihad'] = cfg
    with open(INI, 'w', encoding='utf8') as f:
        parser.write(f)


def already_running() -> bool:
    ctypes.windll.kernel32.CreateMutexW(None, False, 'Local\\EtihadAttendanceReader')
    return ctypes.windll.kernel32.GetLastError() == 183  # ERROR_ALREADY_EXISTS


def stop_running_copy() -> None:
    subprocess.run(['taskkill', '/F', '/IM', APP_NAME + '.exe', '/FI', f'PID ne {os.getpid()}'],
                   stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, creationflags=0x08000000)
    time.sleep(1.5)


def install(cfg: dict) -> None:
    """Copy into AppData, start with Windows, and start reading now."""
    os.makedirs(INSTALL_DIR, exist_ok=True)
    if os.path.abspath(sys.executable).lower() != INSTALLED_EXE.lower():
        stop_running_copy()  # an older copy, when installing again or updating
        shutil.copy2(sys.executable, INSTALLED_EXE)
    save_settings(cfg)
    with winreg.OpenKey(winreg.HKEY_CURRENT_USER, RUN_KEY, 0, winreg.KEY_SET_VALUE) as key:
        winreg.SetValueEx(key, APP_NAME, 0, winreg.REG_SZ, f'"{INSTALLED_EXE}" --background')
    subprocess.Popen([INSTALLED_EXE, '--background'], creationflags=0x00000008 | 0x00000200)  # detached, new group
    message('Installed.\n\nThe attendance reader now runs in the background: it reads the attendance machine every '
            f'{EVERY_MINUTES} minutes and sends new punches to the website, and starts by itself with Windows.\n\n'
            'Check it on the website: Attendance > Attendance machine > "Last sent".')


def uninstall() -> None:
    try:
        with winreg.OpenKey(winreg.HKEY_CURRENT_USER, RUN_KEY, 0, winreg.KEY_SET_VALUE) as key:
            winreg.DeleteValue(key, APP_NAME)
    except OSError:
        pass
    stop_running_copy()
    message('The attendance reader is stopped and will no longer start with Windows.')


def read_machine(ip: str) -> tuple[str, str, int, int]:
    conn = ZK(ip, port=4370, timeout=20, password=0, force_udp=False, ommit_ping=True).connect()
    try:
        users = conn.get_users()
        logs = conn.get_attendance()
    finally:
        conn.disconnect()
    since = datetime.datetime.now() - datetime.timedelta(days=DAYS_BACK)
    user_lines = '\n'.join(f'USER PIN={u.user_id}\tName={(u.name or "").replace(chr(9), " ").strip()}' for u in users)
    recent = [a for a in logs if a.timestamp >= since]
    punch_lines = '\n'.join(f'{a.user_id}\t{a.timestamp:%Y-%m-%d %H:%M:%S}\t{a.status}\t{a.punch}' for a in recent)
    return user_lines, punch_lines, len(users), len(recent)


def send(cfg: dict, users: str, punches: str) -> dict:
    body = urllib.parse.urlencode({'serial': cfg['serial'], 'key': cfg['key'], 'users': users, 'punches': punches}).encode()
    request = urllib.request.Request(cfg['website'].rstrip('/') + '/attendance-sync', data=body,
                                     headers={'Accept': 'application/json', 'User-Agent': 'EtihadAttendanceReader/2.0'})
    try:
        with urllib.request.urlopen(request, timeout=60) as response:
            return json.loads(response.read().decode('utf8'))
    except urllib.error.HTTPError as e:
        try:
            return json.loads(e.read().decode('utf8'))
        except ValueError:
            return {'ok': False, 'error': f'website answered {e.code}'}


def run_once(cfg: dict) -> None:
    try:
        users, punches, user_count, punch_count = read_machine(cfg['machine_ip'])
    except Exception as e:  # noqa: BLE001 - logged, tried again next round
        log(f'Could not read the attendance machine at {cfg["machine_ip"]}: {e} (is this computer on the office Wi-Fi?)')
        return
    try:
        result = send(cfg, users, punches)
    except Exception as e:  # noqa: BLE001
        log(f'Read {punch_count} punches but could not reach the website: {e}')
        return
    log(f'Read {user_count} people, {punch_count} punches. ' + (result.get('message', 'Sent.') if result.get('ok') else 'Website refused: ' + result.get('error', '?')))


def background(cfg: dict) -> None:
    if already_running():
        return
    while True:
        run_once(cfg)
        time.sleep(EVERY_MINUTES * 60)


def main() -> None:
    if '--uninstall' in sys.argv:
        uninstall()
        return

    from_name = settings_from_name(sys.executable)
    if '--background' in sys.argv:
        cfg = load_settings()
        if cfg:
            background(cfg)
        return

    if from_name:
        install(from_name)
        return

    if load_settings():
        message('The attendance reader is installed and running in the background.\n\n'
                'Its log is in: ' + LOG + '\n\nTo stop it, run this program with --uninstall.')
        return

    message('Download this program from the website (Attendance > Attendance machine > Reader program) and '
            'double-click it without renaming it. Its name carries the settings it needs.', error=True)


if __name__ == '__main__':
    main()
