#!/usr/bin/env python3
import argparse
import json
import os
import subprocess
import sys
import tempfile
import time
import urllib.request
from pathlib import Path

CONFIG_PATH = Path("/var/lib/svxlink-ct/meteo-alerts.json")
STATE_PATH = Path("/var/lib/svxlink-ct/meteo-alerts-state.json")
DEFAULT_CONFIG = {
    "enabled": False,
    "interval_minutes": 60,
    "location_id": "LSB",
    "location_label": "Lisboa",
    "area": "LSB",
    "area_label": "Lisboa",
    "credentials": "/home/pi/chave.json",
    "output_wav": "/usr/share/svxlink/sounds/pt_PT/Core/aviso.wav",
    "dtmf_pty": "/tmp/svxlink_dtmf",
    "dtmf_command": "99#",
    "trigger_dtmf": True,
    "api_url": "https://api.ipma.pt/open-data/forecast/warnings/warnings_www.json",
}


def load_json(path, default):
    try:
        with Path(path).open("r", encoding="utf-8") as handle:
            data = json.load(handle)
        if isinstance(data, dict):
            merged = dict(default)
            merged.update(data)
            return merged
    except FileNotFoundError:
        return dict(default)
    except json.JSONDecodeError:
        return dict(default)
    return dict(default)


def save_json(path, data):
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix(path.suffix + ".tmp")
    with tmp.open("w", encoding="utf-8") as handle:
        json.dump(data, handle, ensure_ascii=False, indent=2)
        handle.write("\n")
    tmp.replace(path)


def due(config, state):
    interval = max(5, int(config.get("interval_minutes", 60))) * 60
    last_run = int(state.get("last_run", 0) or 0)
    return time.time() - last_run >= interval


def fetch_warnings(config):
    request = urllib.request.Request(
        str(config["api_url"]),
        headers={"User-Agent": "SVXLINK-CT meteo alerts"},
    )
    with urllib.request.urlopen(request, timeout=20) as response:
        payload = response.read().decode("utf-8")
    data = json.loads(payload)
    if not isinstance(data, list):
        return []
    area = str(config.get("area", "")).upper()
    return [item for item in data if str(item.get("idAreaAviso", "")).upper() == area]


def warning_text(config, warnings):
    label = str(config.get("area_label") or config.get("location_label") or config.get("area"))
    if not warnings:
        return f"Informação IPMA. Não existem avisos meteorológicos ativos para {label}."

    parts = [f"Informação IPMA. Existem {len(warnings)} avisos meteorológicos para {label}."]
    for item in warnings[:5]:
        kind = item.get("awarenessTypeName") or item.get("awarenessType") or "aviso"
        level = item.get("awarenessLevelID") or item.get("awarenessLevelName") or ""
        text = item.get("text") or ""
        start = item.get("startTime") or ""
        end = item.get("endTime") or ""
        sentence = f"Aviso de {kind}"
        if level:
            sentence += f", nível {level}"
        if start or end:
            sentence += f", válido"
            if start:
                sentence += f" desde {start}"
            if end:
                sentence += f" até {end}"
        if text:
            sentence += f". {text}"
        parts.append(sentence)
    return " ".join(parts)


def synthesize(config, text):
    credentials = Path(str(config.get("credentials", "")))
    if not credentials.is_file() or not os.access(credentials, os.R_OK):
        raise RuntimeError(f"Chave Google não encontrada ou sem leitura: {credentials}")
    os.environ["GOOGLE_APPLICATION_CREDENTIALS"] = str(credentials)

    from google.cloud import texttospeech

    client = texttospeech.TextToSpeechClient()
    synthesis_input = texttospeech.SynthesisInput(text=text)
    audio_config = texttospeech.AudioConfig(audio_encoding=texttospeech.AudioEncoding.MP3)
    voices = [
        texttospeech.VoiceSelectionParams(
            language_code="pt-PT",
            name="pt-PT-Wavenet-A",
            ssml_gender=texttospeech.SsmlVoiceGender.FEMALE,
        ),
        texttospeech.VoiceSelectionParams(
            language_code="pt-PT",
            ssml_gender=texttospeech.SsmlVoiceGender.FEMALE,
        ),
    ]
    last_error = None
    response = None
    for voice in voices:
        try:
            response = client.synthesize_speech(input=synthesis_input, voice=voice, audio_config=audio_config)
            break
        except Exception as exc:
            last_error = exc
    if response is None:
        raise RuntimeError(str(last_error) if last_error else "Não foi possível gerar voz pt-PT.")

    output_wav = Path(str(config.get("output_wav", DEFAULT_CONFIG["output_wav"])))
    output_wav.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.NamedTemporaryFile(suffix=".mp3", delete=False) as temp_mp3:
        temp_mp3.write(response.audio_content)
        temp_mp3_path = temp_mp3.name

    tmp_wav = str(output_wav) + ".tmp"
    try:
        subprocess.run(["sox", temp_mp3_path, tmp_wav], check=True)
        os.replace(tmp_wav, output_wav)
    finally:
        try:
            os.unlink(temp_mp3_path)
        except FileNotFoundError:
            pass
        try:
            os.unlink(tmp_wav)
        except FileNotFoundError:
            pass


def trigger_dtmf(config):
    if not config.get("trigger_dtmf", True):
        return
    pty = Path(str(config.get("dtmf_pty", "")))
    command = str(config.get("dtmf_command", "99#")).strip()
    if not command or not pty.exists():
        return
    with pty.open("a", encoding="utf-8") as handle:
        handle.write(command + "\n")


def main():
    parser = argparse.ArgumentParser(description="Avisos meteorológicos IPMA para SVXLINK-CT")
    parser.add_argument("--config", default=str(CONFIG_PATH))
    parser.add_argument("--state", default=str(STATE_PATH))
    parser.add_argument("--due", action="store_true")
    parser.add_argument("--force", action="store_true")
    args = parser.parse_args()

    config = load_json(args.config, DEFAULT_CONFIG)
    state = load_json(args.state, {})

    if not config.get("enabled", False) and not args.force:
        print("Avisos meteorológicos desactivados.")
        return 0
    if args.due and not args.force and not due(config, state):
        print("Ainda não está na hora.")
        return 0

    try:
        warnings = fetch_warnings(config)
        text = warning_text(config, warnings)
        synthesize(config, text)
        trigger_dtmf(config)
        state.update({
            "last_run": int(time.time()),
            "last_status": "ok",
            "last_count": len(warnings),
            "last_text": text,
            "area": config.get("area"),
            "area_label": config.get("area_label"),
        })
        save_json(args.state, state)
        print(text)
        return 0
    except Exception as exc:
        state.update({
            "last_run": int(time.time()),
            "last_status": "erro",
            "last_error": str(exc),
            "area": config.get("area"),
            "area_label": config.get("area_label"),
        })
        save_json(args.state, state)
        print(str(exc), file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
