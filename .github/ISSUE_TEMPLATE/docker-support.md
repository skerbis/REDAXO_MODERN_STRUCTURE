---
name: Docker Support Question
about: Fragen zur Docker-Verwendung
title: '[Docker] '
labels: docker, question
assignees: ''

---

## Docker Setup Information

**Welche Docker-Version verwendest du?**
```bash
docker --version
docker compose version
```

**Welche docker-compose Datei verwendest du?**
- [ ] docker-compose.yml (Produktion)
- [ ] docker-compose.dev.yml (Entwicklung)

**Beschreibung des Problems**
<!-- Beschreibe dein Problem hier -->

**Schritte zur Reproduktion**
1. 
2. 
3. 

**Erwartetes Verhalten**
<!-- Was sollte passieren? -->

**Tatsächliches Verhalten**  
<!-- Was passiert stattdessen? -->

**Container-Logs**
```bash
# Füge die Ausgabe von diesem Befehl ein:
docker compose logs
```

**System Information**
- OS: 
- Docker Version:
- Docker Compose Version:

## Troubleshooting Checklist

Bitte prüfe diese Punkte, bevor du das Issue einreichst:

- [ ] Container laufen: `docker compose ps`
- [ ] Ports sind frei: `netstat -tulpn | grep :8080`
- [ ] Logs geprüft: `docker compose logs -f`
- [ ] [Docker Quickstart Guide](DOCKER.md) gelesen
- [ ] [Docker Examples](DOCKER-EXAMPLES.md) geprüft