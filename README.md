# CRUD Basic Laravel – Deploy to AWS EC2 (Step by Step)

This project is a **Laravel 10 REST API** (Products, Categories, Posts, Users) that uses **MySQL**.
This guide shows you how to put it on the internet using an **AWS EC2** server running **Ubuntu 24.04**, **Nginx**, **PHP 8.3** and **MySQL**.

> ⏱️ Time needed: about 45–60 minutes
> 💰 Cost: free if your AWS account is still in the Free Tier (use `t2.micro` or `t3.micro`)

---

## 📋 What you need before starting

- [ ] An **AWS account** (https://aws.amazon.com)
- [ ] This project pushed to **your own GitHub repository**
- [ ] A terminal:
  - Windows: **PowerShell** or **Git Bash**
  - macOS / Linux: **Terminal**

---

## 🗺️ The big picture

```
Your browser / Postman
        │  http://<EC2-PUBLIC-IP>/api/v1/products
        ▼
┌──────────────── AWS EC2 (Ubuntu) ────────────────┐
│  Nginx  ──►  PHP-FPM  ──►  Laravel  ──►  MySQL   │
└──────────────────────────────────────────────────┘
```

We will do it in **10 steps**:

1. Create the EC2 server
2. Connect to the server with SSH
3. Install the software (Nginx, PHP, MySQL, Composer)
4. Create the database
5. Download the project from GitHub
6. Configure the `.env` file
7. Install dependencies and run migrations
8. Set folder permissions
9. Configure Nginx
10. Test the API 🎉

---

## Step 1 – Create the EC2 server

1. Log in to the **AWS Console** → search for **EC2** → click **Launch instance**.
2. Fill in the form:

   | Setting | Value |
   |---|---|
   | **Name** | `laravel-crud-server` |
   | **AMI (Operating System)** | **Ubuntu Server 24.04 LTS** |
   | **Instance type** | `t2.micro` or `t3.micro` (Free tier eligible) |
   | **Key pair** | Click **Create new key pair** → name `laravel-key` → type **RSA** → format **.pem** → **Create**. The file `laravel-key.pem` downloads. **Keep it safe – you cannot download it again!** |
   | **Storage** | 8–20 GB is enough |

3. Under **Network settings**, click **Edit** and tick these 3 boxes:
   - ✅ Allow **SSH** traffic from → **My IP**
   - ✅ Allow **HTTP** traffic from the internet
   - ✅ Allow **HTTPS** traffic from the internet
4. Click **Launch instance**.
5. Go to **Instances**, click your instance, and copy the **Public IPv4 address** (example: `13.250.10.20`).
   We will call it `<EC2-PUBLIC-IP>` in this guide.

> 💡 **Tip:** The public IP changes every time you **stop/start** the instance. To keep a fixed IP, go to **EC2 → Elastic IPs → Allocate** and then **Associate** it with your instance.

---

## Step 2 – Connect to the server with SSH

Open your terminal in the folder where `laravel-key.pem` was downloaded.

**macOS / Linux / Git Bash:**

```bash
chmod 400 laravel-key.pem
ssh -i laravel-key.pem ubuntu@<EC2-PUBLIC-IP>
```

**Windows PowerShell** (if you get a "permissions are too open" error, run these first):

```powershell
icacls .\laravel-key.pem /inheritance:r
icacls .\laravel-key.pem /grant:r "$($env:USERNAME):(R)"
ssh -i .\laravel-key.pem ubuntu@<EC2-PUBLIC-IP>
```

Type `yes` when asked *"Are you sure you want to continue connecting?"*

✅ You are connected when you see a prompt like: `ubuntu@ip-172-31-xx-xx:~$`

> From now on, **all commands run on the server**.

---

## Step 3 – Install the software

### 3.1 Update the server

```bash
sudo apt update && sudo apt upgrade -y
```

### 3.2 Install Nginx, PHP 8.3, MySQL, Git and unzip

```bash
sudo apt install -y nginx mysql-server git unzip curl \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl
```

Check the versions:

```bash
php -v        # should show PHP 8.3.x
nginx -v
mysql --version
```

### 3.3 Install Composer (PHP package manager)

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### 3.4 Install Node.js (only needed to build front-end assets with Vite)

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v
```

> This project is mainly an API, so Node.js is optional. Install it anyway so `npm run build` works if you add pages later.

### 3.5 Open the browser to test Nginx

Visit `http://<EC2-PUBLIC-IP>` – you should see **"Welcome to nginx!"** ✅

---

## Step 4 – Create the database

Open MySQL as the admin user:

```bash
sudo mysql
```

Run these SQL commands (change `StrongPassword123!` to your own password):

```sql
CREATE DATABASE laravel_crud;
CREATE USER 'laravel_user'@'localhost' IDENTIFIED BY 'StrongPassword123!';
GRANT ALL PRIVILEGES ON laravel_crud.* TO 'laravel_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Test the new user:

```bash
mysql -u laravel_user -p
# type the password, then:
SHOW DATABASES;   -- you should see laravel_crud
EXIT;
```

---

## Step 5 – Download the project from GitHub

```bash
cd /var/www
sudo git clone https://github.com/<your-username>/crud-basic-laravel25.git
sudo chown -R ubuntu:www-data /var/www/crud-basic-laravel25
cd /var/www/crud-basic-laravel25
```

> 🔒 If your repository is **private**, GitHub will ask for a username and a **Personal Access Token** (not your password). Create one at GitHub → Settings → Developer settings → Personal access tokens.

---

## Step 6 – Configure the `.env` file

```bash
cp .env.example .env
nano .env
```

Change these lines (leave the others as they are):

```env
APP_NAME="CRUD Basic Laravel"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://<EC2-PUBLIC-IP>

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_crud
DB_USERNAME=laravel_user
DB_PASSWORD=StrongPassword123!
```

Save and exit nano: press **Ctrl + O**, **Enter**, then **Ctrl + X**.

> ⚠️ **Never** set `APP_DEBUG=true` on a public server – it shows your passwords and code to everyone when an error happens.
> ⚠️ **Never** commit the `.env` file to GitHub.

---

## Step 7 – Install dependencies and run migrations

```bash
# 1. Install PHP packages (no dev tools in production)
composer install --no-dev --optimize-autoloader

# 2. Generate the application key (fills APP_KEY in .env)
php artisan key:generate

# 3. Create the tables in MySQL
php artisan migrate --force

# 4. (Optional) Build front-end assets
npm install
npm run build

# 5. Cache config and routes for better speed
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

✅ After `migrate` you should see tables such as `users`, `products`, `categories`, `posts`, `profiles`, `students`, `courses`, `student_course` being created.

> 💡 If you change `.env` later, run `php artisan config:clear` and then `php artisan config:cache` again, otherwise Laravel keeps using the old values.

---

## Step 8 – Set folder permissions

Laravel must be able to write to `storage` and `bootstrap/cache`. Nginx/PHP runs as the user **www-data**.

```bash
sudo chown -R ubuntu:www-data /var/www/crud-basic-laravel25
sudo chmod -R 775 /var/www/crud-basic-laravel25/storage
sudo chmod -R 775 /var/www/crud-basic-laravel25/bootstrap/cache
```

---

## Step 9 – Configure Nginx

Create a new site configuration:

```bash
sudo nano /etc/nginx/sites-available/laravel
```

Paste this (replace `<EC2-PUBLIC-IP>`):

```nginx
server {
    listen 80;
    server_name <EC2-PUBLIC-IP>;

    root /var/www/crud-basic-laravel25/public;
    index index.php index.html;

    charset utf-8;
    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

> ❗ The `root` **must** end with `/public`. Pointing it at the project folder would expose your `.env` file.

Enable the site, disable the default one, test and reload:

```bash
sudo ln -s /etc/nginx/sites-available/laravel /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t                 # must say "syntax is ok" and "test is successful"
sudo systemctl reload nginx
```

---

## Step 10 – Test the API 🎉

Open in the browser:

- `http://<EC2-PUBLIC-IP>/` → Laravel welcome page
- `http://<EC2-PUBLIC-IP>/api/v1/products` → `[]` or a JSON list

Test with **Postman** (or `curl`):

| Method | URL | Action |
|---|---|---|
| GET | `/api/v1/products` | List all products |
| POST | `/api/v1/products` | Create a product |
| GET | `/api/v1/products/{id}` | Show one product |
| PUT | `/api/v1/products/{id}` | Update a product |
| DELETE | `/api/v1/products/{id}` | Delete a product |

The same 5 routes exist for **`categories`**, **`posts`** and **`users`**.

> 💡 In Postman, add the header `Accept: application/json` so Laravel returns validation errors as JSON instead of redirecting.

See all routes on the server:

```bash
php artisan route:list --path=api
```

**Congratulations – your Laravel API is live on AWS! 🚀**

---

## 🔄 How to update the server after you change your code

On your computer:

```bash
git add .
git commit -m "my changes"
git push
```

On the server:

```bash
cd /var/www/crud-basic-laravel25
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl reload php8.3-fpm
```

---

## 🛠️ Troubleshooting

| Problem | What to check / do |
|---|---|
| **SSH: Connection timed out** | Security Group must allow port **22** from *your current IP* (your IP may have changed – edit the inbound rule). Is the instance **Running**? |
| **SSH: Permission denied (publickey)** | Use user `ubuntu` (not `root`), and the correct `.pem` file. Run `chmod 400 laravel-key.pem`. |
| **Browser can't reach the site** | Security Group must allow **HTTP (port 80)** from `0.0.0.0/0`. Use `http://`, not `https://`. |
| **502 Bad Gateway** | PHP-FPM not running: `sudo systemctl status php8.3-fpm`. Check the socket path in Nginx is `php8.3-fpm.sock`. |
| **404 Not Found on `/api/...`** | Nginx `root` must end with `/public` and `try_files` line must be present. Run `php artisan route:clear`. |
| **500 Server Error** | Read the log: `tail -n 50 storage/logs/laravel.log` |
| **"Permission denied" in `storage/logs`** | Re-run the commands in **Step 8**. |
| **SQLSTATE[HY000] [1045] Access denied** | Wrong `DB_USERNAME` / `DB_PASSWORD` in `.env`. After fixing, run `php artisan config:clear`. |
| **"No application encryption key"** | Run `php artisan key:generate` then `php artisan config:cache`. |
| **Changes in `.env` are ignored** | Run `php artisan config:clear && php artisan config:cache`. |

Useful log locations:

```bash
tail -f storage/logs/laravel.log          # Laravel errors
sudo tail -f /var/log/nginx/error.log      # Nginx errors
```

---

## ⭐ Bonus (optional)

### A. Use a domain name + free HTTPS (Let's Encrypt)

1. Buy/use a domain and create an **A record** pointing to your **Elastic IP**.
2. Change `server_name` in the Nginx config to your domain (e.g. `api.example.com`) and reload Nginx.
3. Run:

   ```bash
   sudo apt install -y certbot python3-certbot-nginx
   sudo certbot --nginx -d api.example.com
   ```
4. Update `APP_URL=https://api.example.com` in `.env` and run `php artisan config:cache`.

### B. Use Amazon RDS instead of MySQL on the server

1. AWS Console → **RDS** → **Create database** → **MySQL** → **Free tier** template.
2. Set master username/password, and under **Connectivity** choose the same VPC as your EC2.
3. In the RDS **Security Group**, allow **MySQL/Aurora (port 3306)** from the **EC2 Security Group**.
4. Create the database `laravel_crud` (connect from EC2 with `mysql -h <RDS-ENDPOINT> -u admin -p`).
5. In `.env` set `DB_HOST=<RDS-ENDPOINT>` and the RDS username/password, then:

   ```bash
   php artisan config:cache
   php artisan migrate --force
   ```

---

## 🧹 Clean up (avoid AWS charges!)

When the class project is finished:

1. **EC2 → Instances** → select your instance → **Instance state → Terminate**.
2. **EC2 → Elastic IPs** → **Release** any Elastic IP (an unused Elastic IP **costs money**).
3. **RDS** (if you used it) → delete the database.

---

## ✅ Final checklist

- [ ] EC2 instance is running and I can SSH into it
- [ ] Nginx, PHP 8.3, MySQL and Composer are installed
- [ ] Database `laravel_crud` and user `laravel_user` are created
- [ ] Project is cloned to `/var/www/crud-basic-laravel25`
- [ ] `.env` is configured (`APP_ENV=production`, `APP_DEBUG=false`, DB settings)
- [ ] `composer install`, `key:generate` and `migrate` ran without errors
- [ ] `storage` and `bootstrap/cache` are writable
- [ ] Nginx `root` points to `/public`
- [ ] `http://<EC2-PUBLIC-IP>/api/v1/products` returns JSON 🎉
