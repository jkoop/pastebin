# Pastebin

This is my simple pastebin server.

![](screenshot.png)

Features:

- password for paster
- no password for readers
- no list of pastes
- short random path
- unambiguous paths
- TODO: file deduplication on disk

## Usage

- **Browser:** Go to https://p.example.com/ and log in with any username and your password
- **curl**: _The content type will not be set; a receiving browser will probably just download it without displaying it_
  ```sh
  curl -F 'file=@path/to/file' 'https://anyuser:Change-This-Passphrase@p.example.com'
  ```

## Install

1. clone the repo and `cd` into it
2. `cp docker-compose.override{-example,}.yml`
3. edit docker-compose.override.yml as desired
4. `mkdir data`
5. `chmod a+w data`
6. `docker compose up -d`
