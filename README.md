# MataSecret

A web app for securely sharing ephemeral secrets with members of your organization.

The name MataSecret comes from:

- "Mata", from the Spanish verb "matar" (transitive), which means "to kill".
- "Mata", from [Mata Hari](https://en.wikipedia.org/wiki/Mata_Hari), a famous Dutch spy during World War I.

and

- "Secret".


## How it works

MataSecret uses an authentication provider that allows users from your organization to create and share secrets that are available for a limited period of time or until they are read.

The web app is stateless: it doesn't require any database or file to store secrets. The secrets are kept in server memory, so if the server is restarted, all secrets will be lost.

The secrets are double-encrypted in memory. One of the encryption keys is provided by the app and the other is located in the share link. This makes it difficult for an intruder to access the secrets, because they would need to:
- Have the share link.
- Have the app key.
- Access the server memory.


## Secret stores

The following in-memory stores are available:


### APCu (**apcu**)

The secrets are stored in the main memory of the web server process, which means that if the web server is restarted or stopped, all secrets will be lost.

This secret store is ideal against buffer overflow attacks that kill the main process.

It's recommended for use with the FrankenPHP server.


### SHMOP (**shmop**)

The secrets are stored in shared memory, so they survive server restarts and stops.

It's recommended for use with a PHP-FPM server that uses multiple pools.


### Redis

The secrets are stored in a Redis server, so they survive server restarts and stops. The Redis server can be located on a different machine.

It's recommended for big organizations that may have redundant servers and may need to persist data to disk (Redis can be configured to do so).


## Installation and setup

Just type:

```sh
composer setup
```
