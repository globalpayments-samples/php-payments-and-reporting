# Deployment Guide

This guide covers deploying the Heartland Payment Integration system to various environments including production, staging, and development setups.

## Table of Contents

- [System Requirements](#system-requirements)
- [Environment Preparation](#environment-preparation)
- [Production Deployment](#production-deployment)
- [Docker Deployment](#docker-deployment)
- [Cloud Deployment](#cloud-deployment)
- [Security Configuration](#security-configuration)
- [Monitoring and Maintenance](#monitoring-and-maintenance)
- [Troubleshooting](#troubleshooting)

## System Requirements

### Minimum Requirements

- **PHP**: 8.0 or higher
- **Memory**: 512MB RAM
- **Storage**: 1GB free space
- **Web Server**: Apache 2.4+ or Nginx 1.18+

### Recommended Requirements

- **PHP**: 8.2 or higher with OPcache enabled
- **Memory**: 2GB RAM
- **Storage**: 5GB free space (with logs and backups)
- **Web Server**: Nginx 1.20+ with SSL/TLS

### PHP Extensions

Required extensions:
- `curl` - For API communication
- `json` - For JSON processing
- `mbstring` - For string handling
- `openssl` - For SSL/TLS connections
- `zip` - For Composer operations

Optional but recommended:
- `opcache` - For performance optimization
- `apcu` - For application-level caching

## Environment Preparation

### 1. Server Setup

#### Ubuntu/Debian
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP and required extensions
sudo apt install php8.2 php8.2-fpm php8.2-curl php8.2-json \
                 php8.2-mbstring php8.2-zip php8.2-opcache \
                 nginx certbot python3-certbot-nginx

# Install Composer
curl -sS https://getcomposer.org/installer | sudo php -- \
    --install-dir=/usr/local/bin --filename=composer
```

#### CentOS/RHEL
```bash
# Install EPEL and Remi repositories
sudo dnf install epel-release
sudo dnf install https://rpms.remirepo.net/enterprise/remi-release-8.rpm

# Install PHP and extensions
sudo dnf module enable php:remi-8.2
sudo dnf install php php-fpm php-curl php-json php-mbstring \
                 php-zip php-opcache nginx certbot python3-certbot-nginx

# Install Composer
curl -sS https://getcomposer.org/installer | sudo php -- \
    --install-dir=/usr/local/bin --filename=composer
```

### 2. User and Directory Setup

```bash
# Create application user
sudo useradd -m -s /bin/bash heartland
sudo usermod -aG www-data heartland

# Create application directory
sudo mkdir -p /var/www/heartland-payment
sudo chown heartland:www-data /var/www/heartland-payment
sudo chmod 755 /var/www/heartland-payment
```

## Production Deployment

### 1. Application Deployment

```bash
# Switch to application user
sudo su - heartland

# Navigate to application directory
cd /var/www/heartland-payment

# Clone repository (or upload files)
git clone <repository-url> .
# OR
# Upload files via SCP/SFTP

# Install dependencies (production mode)
composer install --no-dev --optimize-autoloader --no-interaction

# Set file permissions
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod -R 755 logs/
chmod +x tools/*.php
```

### 2. Environment Configuration

```bash
# Create production environment file
cp .env.example .env

# Edit environment file with production values
vim .env
```

**Production .env configuration:**
```env
# GlobalPayments Production Configuration
SECRET_API_KEY=your_production_secret_api_key
DEVELOPER_ID=your_production_developer_id
VERSION_NUMBER=your_production_version_number
SERVICE_URL=https://api2.heartlandportico.com

# Environment Settings
APP_ENV=production
ENABLE_REQUEST_LOGGING=false

# Security Settings
SESSION_TIMEOUT=1800
MAX_REQUEST_SIZE=10M
```

### 3. Web Server Configuration

#### Nginx Configuration

Create `/etc/nginx/sites-available/heartland-payment`:

```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    
    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com www.your-domain.com;
    
    root /var/www/heartland-payment/public;
    index index.html index.php;
    
    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    
    # Security Headers
    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload";
    
    # API endpoints
    location ~ ^/api/(.+\.php)$ {
        try_files $uri =404;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/heartland-payment/api/$1;
        include fastcgi_params;
        fastcgi_param HTTPS on;
    }
    
    # Static files
    location / {
        try_files $uri $uri/ =404;
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # Security: Block access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ ^/(vendor|tests|logs|tools)/ {
        deny all;
    }
    
    location ~ \.(env|json|lock)$ {
        deny all;
    }
    
    # Logging
    access_log /var/log/nginx/heartland-payment.access.log;
    error_log /var/log/nginx/heartland-payment.error.log;
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/heartland-payment /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

#### Apache Configuration

Create `/etc/apache2/sites-available/heartland-payment.conf`:

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    ServerAlias www.your-domain.com
    Redirect permanent / https://your-domain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName your-domain.com
    ServerAlias www.your-domain.com
    DocumentRoot /var/www/heartland-payment/public
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/your-domain.com/cert.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/your-domain.com/privkey.pem
    SSLCertificateChainFile /etc/letsencrypt/live/your-domain.com/chain.pem
    
    # Security Headers
    Header always set X-Frame-Options DENY
    Header always set X-Content-Type-Options nosniff
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
    
    # API Directory
    Alias /api /var/www/heartland-payment/api
    <Directory "/var/www/heartland-payment/api">
        Options -Indexes
        AllowOverride None
        Require all granted
        
        <FilesMatch "\.php$">
            SetHandler "proxy:unix:/run/php/php8.2-fpm.sock|fcgi://localhost"
        </FilesMatch>
    </Directory>
    
    # Public Directory
    <Directory "/var/www/heartland-payment/public">
        Options -Indexes
        AllowOverride None
        Require all granted
    </Directory>
    
    # Security: Block sensitive directories
    <DirectoryMatch "/(vendor|tests|logs|tools|src)">
        Require all denied
    </DirectoryMatch>
    
    # Logging
    CustomLog /var/log/apache2/heartland-payment.access.log combined
    ErrorLog /var/log/apache2/heartland-payment.error.log
</VirtualHost>
```

Enable the site:
```bash
sudo a2enmod ssl headers rewrite
sudo a2ensite heartland-payment
sudo systemctl reload apache2
```

### 4. SSL Certificate

```bash
# Obtain Let's Encrypt certificate
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# Test automatic renewal
sudo certbot renew --dry-run
```

### 5. PHP-FPM Configuration

Edit `/etc/php/8.2/fpm/pool.d/www.conf`:

```ini
; Production optimizations
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 1000

; Security
php_admin_value[expose_php] = Off
php_admin_value[allow_url_fopen] = Off
```

Edit `/etc/php/8.2/fpm/php.ini`:

```ini
; Production settings
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log

; Security
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off

; Performance
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0

; Upload limits
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 30
memory_limit = 256M
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.2-fpm
```

## Docker Deployment

### 1. Dockerfile

Create `Dockerfile`:

```dockerfile
FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    zip \
    unzip \
    git

# Install PHP extensions
RUN docker-php-ext-install \
    curl \
    opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create application user
RUN addgroup -g 1000 heartland && \
    adduser -u 1000 -G heartland -s /bin/sh -D heartland

# Set working directory
WORKDIR /var/www/heartland-payment

# Copy application files
COPY --chown=heartland:heartland . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Configure Nginx
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php.ini /usr/local/etc/php/php.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Set permissions
RUN chmod -R 755 /var/www/heartland-payment && \
    chown -R heartland:heartland /var/www/heartland-payment && \
    chmod -R 755 logs/

EXPOSE 80 443

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

### 2. Docker Compose

Create `docker-compose.yml`:

```yaml
version: '3.8'

services:
  heartland-payment:
    build: .
    ports:
      - "80:80"
      - "443:443"
    environment:
      - APP_ENV=production
      - SECRET_API_KEY=${SECRET_API_KEY}
      - DEVELOPER_ID=${DEVELOPER_ID}
      - VERSION_NUMBER=${VERSION_NUMBER}
      - SERVICE_URL=${SERVICE_URL}
    volumes:
      - ./logs:/var/www/heartland-payment/logs
      - ssl_certs:/etc/ssl/certs
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/"]
      interval: 30s
      timeout: 10s
      retries: 3

volumes:
  ssl_certs:
```

### 3. Deploy with Docker

```bash
# Build and start
docker-compose up -d

# View logs
docker-compose logs -f

# Update deployment
docker-compose pull
docker-compose up -d --build
```

## Cloud Deployment

### AWS EC2 Deployment

1. **Launch EC2 Instance**
   - Choose Amazon Linux 2 or Ubuntu 20.04 LTS
   - Use t3.small or larger
   - Configure security groups (ports 80, 443, 22)

2. **Configure Load Balancer**
   ```bash
   # Install AWS CLI and configure
   aws configure
   
   # Create Application Load Balancer
   aws elbv2 create-load-balancer \
     --name heartland-payment-lb \
     --subnets subnet-12345 subnet-67890 \
     --security-groups sg-12345
   ```

3. **Auto Scaling Setup**
   ```bash
   # Create launch template
   aws ec2 create-launch-template \
     --launch-template-name heartland-payment-template \
     --launch-template-data file://launch-template.json
   ```

### Google Cloud Platform

1. **Create Compute Engine Instance**
   ```bash
   gcloud compute instances create heartland-payment \
     --image-family=ubuntu-2004-lts \
     --image-project=ubuntu-os-cloud \
     --machine-type=e2-small \
     --tags=http-server,https-server
   ```

2. **Configure Load Balancer**
   ```bash
   gcloud compute backend-services create heartland-payment-backend \
     --global
   ```

### Microsoft Azure

1. **Create Virtual Machine**
   ```bash
   az vm create \
     --resource-group heartland-payment-rg \
     --name heartland-payment-vm \
     --image UbuntuLTS \
     --admin-username azureuser \
     --generate-ssh-keys
   ```

## Security Configuration

### 1. Firewall Setup

```bash
# Ubuntu/Debian (UFW)
sudo ufw enable
sudo ufw allow 22
sudo ufw allow 80
sudo ufw allow 443

# CentOS/RHEL (firewalld)
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

### 2. Fail2Ban Configuration

```bash
# Install Fail2Ban
sudo apt install fail2ban  # Ubuntu/Debian
sudo dnf install fail2ban  # CentOS/RHEL

# Configure jail
sudo vim /etc/fail2ban/jail.local
```

Add configuration:
```ini
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[nginx-http-auth]
enabled = true

[nginx-limit-req]
enabled = true
```

### 3. Log Monitoring

```bash
# Install logwatch
sudo apt install logwatch

# Configure daily reports
sudo vim /etc/cron.daily/00logwatch
```

## Monitoring and Maintenance

### 1. Health Checks

Create `tools/health-check.php`:

```php
<?php
// System health check
$checks = [
    'php_version' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'extensions' => extension_loaded('curl') && extension_loaded('json'),
    'writable_logs' => is_writable(__DIR__ . '/../logs'),
    'api_connection' => false // Implement API connectivity check
];

header('Content-Type: application/json');
echo json_encode([
    'status' => array_reduce($checks, fn($carry, $check) => $carry && $check, true) ? 'healthy' : 'unhealthy',
    'checks' => $checks,
    'timestamp' => date('c')
]);
```

### 2. Log Rotation

Create `/etc/logrotate.d/heartland-payment`:

```
/var/www/heartland-payment/logs/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 heartland www-data
    postrotate
        systemctl reload php8.2-fpm
    endscript
}
```

### 3. Backup Strategy

```bash
#!/bin/bash
# backup.sh

BACKUP_DIR="/backup/heartland-payment"
APP_DIR="/var/www/heartland-payment"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Backup application files (excluding vendor and logs)
tar -czf "$BACKUP_DIR/app_$DATE.tar.gz" \
    --exclude='vendor' \
    --exclude='logs' \
    --exclude='.git' \
    -C "$APP_DIR" .

# Backup configuration
cp "$APP_DIR/.env" "$BACKUP_DIR/env_$DATE"

# Backup logs (last 7 days)
tar -czf "$BACKUP_DIR/logs_$DATE.tar.gz" \
    -C "$APP_DIR/logs" \
    --newer-mtime='7 days ago' .

# Cleanup old backups (keep 30 days)
find "$BACKUP_DIR" -type f -mtime +30 -delete
```

### 4. Monitoring Setup

Install monitoring tools:

```bash
# Install New Relic (optional)
curl -Ls https://download.newrelic.com/php_agent/release/newrelic-php5-x.x.x-linux.tar.gz | tar -C /tmp -zx
cd /tmp/newrelic-php5-*
sudo ./newrelic-install install

# Configure monitoring
sudo vim /etc/php/8.2/fpm/conf.d/newrelic.ini
```

## Troubleshooting

### Common Issues

1. **Permission Errors**
   ```bash
   # Fix file permissions
   sudo chown -R heartland:www-data /var/www/heartland-payment
   sudo chmod -R 755 /var/www/heartland-payment
   sudo chmod -R 755 /var/www/heartland-payment/logs
   ```

2. **PHP-FPM Socket Errors**
   ```bash
   # Check PHP-FPM status
   sudo systemctl status php8.2-fpm
   
   # Check socket file
   ls -la /run/php/php8.2-fpm.sock
   ```

3. **SSL Certificate Issues**
   ```bash
   # Test certificate
   sudo certbot certificates
   
   # Renew certificate
   sudo certbot renew
   ```

4. **API Connection Issues**
   ```bash
   # Test connectivity
   curl -I https://cert.api2.heartlandportico.com
   
   # Check DNS resolution
   nslookup cert.api2.heartlandportico.com
   ```

### Log Analysis

```bash
# Check application logs
tail -f /var/www/heartland-payment/logs/transaction-errors.log

# Check web server logs
tail -f /var/log/nginx/heartland-payment.error.log

# Check PHP logs
tail -f /var/log/php/error.log

# Check system logs
journalctl -u nginx -f
journalctl -u php8.2-fpm -f
```

### Performance Optimization

1. **Enable OPcache**
   ```ini
   ; In php.ini
   opcache.enable=1
   opcache.memory_consumption=256
   opcache.max_accelerated_files=10000
   opcache.validate_timestamps=0
   ```

2. **Optimize Nginx**
   ```nginx
   # In nginx.conf
   worker_processes auto;
   worker_connections 1024;
   
   gzip on;
   gzip_types text/plain text/css application/json application/javascript;
   ```

3. **Database Optimization** (if applicable)
   ```bash
   # Monitor slow queries
   # Optimize indexes
   # Configure connection pooling
   ```

For additional support, check the [main documentation](../README.md) or review the application logs.