#!/usr/bin/sh

cat <<EOF
    mail:
        build: docker/mail
        env_file:
          - ./docker/mail/mail.env
        ports:
          - 25:25
          - 110:110
          - 143:143
          - 587:587
          - 993:993
        volumes:
          - ./docker/mail/mail:/var/mail
          - ./docker/mail/config:/etc/mail
          # Certbot
          - ./docker/certbot/www:/var/www/certbot
          - ./docker/certbot/.files:/etc/letsencrypt

EOF
