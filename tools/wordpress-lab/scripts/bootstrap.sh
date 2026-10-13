#!/bin/sh
set -eu
if ! wp core is-installed >/dev/null 2>&1; then
    wp core install --url="$LAB_URL" --title="WordPress Lab" \
        --admin_user="$WORDPRESS_ADMIN_USER" --admin_password="$WORDPRESS_ADMIN_PASSWORD" \
        --admin_email="$WORDPRESS_ADMIN_EMAIL" --skip-email
fi
if ! wp plugin is-active netchat >/dev/null 2>&1; then
    wp plugin activate netchat
fi
wp eval-file /lab-scripts/setup-netchat.php
