# ChatConnect

A responsive PHP messaging app using SQLite and Docker.

## Requirements
- Docker Desktop
- A modern browser

## Start
From this directory run:
```bash
docker compose up --build -d
```
Open http://localhost:8081

## Stop
```bash
docker compose down
```

## Persistent data
SQLite database is stored in the Docker volume `chatconnect_data`.
Uploaded files are stored in `uploads/` in the project folder.

## First administrator
Register the account normally, then promote it from the SQLite database:
```sql
UPDATE users SET role='admin' WHERE email='your-email@example.com';
```
You can access the admin user listing by calling the authenticated API action `admin_users`. An admin UI can be added in the next iteration.

## Notes
- Messaging uses authenticated HTTP polling in this starter implementation.
- WebRTC calling requires camera/microphone permissions and HTTPS for non-localhost deployments.
- For calls between different networks, configure a TURN server; STUN alone is not sufficient for every network.
- Change the exposed port in `docker-compose.yml` if 8081 is occupied.
