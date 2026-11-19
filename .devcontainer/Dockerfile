FROM mcr.microsoft.com/devcontainers/base:noble

RUN apt-get update \
    && apt-get install -y --no-install-recommends php-cli php-curl php-gd php-mbstring php-mysql \
    && rm -rf /var/lib/apt/lists/*
