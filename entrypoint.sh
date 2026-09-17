#!/bin/bash

# Script de inicialização para ajustar permissões
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

# Inicializa o Apache
apache2-foreground
