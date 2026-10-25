FROM php:8.2-apache

# ติดตั้งส่วนขยาย cURL สำหรับเชื่อมต่อ Supabase
RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

# เปิดใช้งาน Apache rewrite
RUN a2enmod rewrite

# ตั้งค่า Apache ให้รับการเชื่อมต่อบนพอร์ต 10000
RUN sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:10000>/' \
       /etc/apache2/sites-available/000-default.conf

# คัดลอกไฟล์เว็บไซต์ทั้งหมดเข้าเว็บเซิร์ฟเวอร์
COPY . /var/www/html/

# ตั้งค่าสิทธิ์ไฟล์
RUN chown -R www-data:www-data /var/www/html

EXPOSE 10000
