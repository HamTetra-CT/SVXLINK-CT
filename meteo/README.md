# Avisos meteorológicos IPMA

O runner `meteo_alerts.py` lê a configuração em:

```text
/var/lib/svxlink-ct/meteo-alerts.json
```

Por defeito usa:

- API IPMA: `https://api.ipma.pt/open-data/forecast/warnings/warnings_www.json`
- Chave Google Text-to-Speech: `/home/pi/chave.json`
- Áudio gerado: `/usr/share/svxlink/sounds/pt_PT/Core/aviso.wav`
- DTMF para tocar o aviso: `99#` em `/tmp/svxlink_dtmf`

A chave `chave.json` é secreta e fica só na máquina instalada. Não deve ser enviada para o repositório.
