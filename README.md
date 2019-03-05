# Magento Deployer Recipes
Install magento recipe(s) for [deployer](http://deployer.org/)

### Usage Example:

<p align="center">
  <img width="980" src="https://rawgit.com/swissup/deployer/master/example.svg">
</p>

### Pre-Install

#### On local machine
1. Install Deployer
    To install Deployer download [deployer.phar](http://deployer.org/deployer.phar) archive and move deployer.phar to your bin
    directory and make it executable.

    ```sh
    ➜ $ curl -L http://deployer.org/deployer.phar -o deployer.phar
    ➜ mv deployer.phar /usr/local/bin/dep
    ➜ chmod +x /usr/local/bin/dep
    ```
### Install

Clone this repository

```bash
➜ git clone git@github.com:swissup/deployer-recipes.git
➜ cd deployer-recipes
➜ cp hosts.yml.example hosts.yml
```
Add your credentials to hosts.yml

```yml
store.com:
  host: store.com
  user: ec2-user
  port: 22
  identityFile: ~/.ssh/key.pem
  forwardAgent: true
  multiplexing: true
  stage: production
  deploy_path: /var/www/html
  mysql_user: user
  mysql_pass: pass
  base_url: http://store.com
  writable_use_sudo: true
  add_sample_data: true # install sample data
  packages: swissup/firecheckout # install custom composer packages
```

Check your ssh connection

```bash
➜ dep config:hosts
➜ dep config:dump store.com
➜ dep ssh store.com -v
[ec2-user@ip-10-149-126-215 current]$ pwd
/var/www/html
[ec2-user@ip-10-149-126-215 current]$ exit
Connection to store.com closed.
```

### Usage

#### Init magento 2 (Install magento 2)

~~~bash
➜ dep magento2:init store.com
➜ dep magento2:init --packages=swissup/ajaxpro,swissup/ajaxlayerednavigation,swissup/firecheckout -v store.com
➜ dep magento2:releases:list store.com
~~~

If every think goes well, deployer will create next structure on remote host in deploy_path:

```bash
.
├── .dep
├── magento2-sample-data
├── current -> /var/www/html/releases/225020180813032114
├── releases
│   └── 225020180813032114
└── shared
    ├── app
    ├── pub
    └── var
```

 - releases dir contains deploy releases of magento application,
 - shared dir contains config, var and pub resouyrces which will be symlinked to each release
 - current is symlink to last release,
 - .dep dir contains special metadata for deployer (lock, releases log, deploy.log file, etc).

Configure your server to serve files from the current folder. For example if you are using [nginx](https://github.com/magento/magento2/blob/2.2-develop/nginx.conf.sample#L11) next:

~~~conf

server {
    listen 80;
    server_name mage.dev;
    set $MAGE_ROOT /var/www/html/current;
    include /vagrant/magento2/nginx.conf.sample;
}

~~~

Now you will be able to serve your project.

#### Deploy (Update code)

~~~bash
➜ dep magento2:deploy store.com -vv
~~~

#### List releasses

~~~bash
➜ dep releases:list store.com
➜ dep releases:list store.com -vv
➜ dep magento2:releases:list store.com
~~~

#### Back up & Rool back

~~~bash
➜ dep magento2:backup
➜ dep magento2:snapshot:list
1531827423 | 2018-07-17 14:37:03
➜ dep magento2:rollback --snapshot=1531827423
➜ dep magento2:rollback --release=193900000000000000 --snapshot=1531827423
➜ dep magento2:rollback --release=193900000000000000
~~~

#### Create magento 1
~~~bash
➜ dep magento:init
➜ dep magento:init --packages=tm/ajax-pro,tm/ajax-layered-navigation,tm/ajax-search,tm/ask-it,tm/easy-banner,tm/helpdesk,tm/navigation-pro,tm/cache,tm/highlight,tm/pro-labels,tm/review-reminder,tm/sold-together

➜ dep magento:releases:list
~~~

#### Clear all

~~~bash
➜ dep releases:remove:all [hostname]
~~~

#### Create skeleton

~~~bash

➜ dep magento2:skeleton:create --packages=swissup/ajaxpro,swissup/ajaxlayerednavigation,swissup/firecheckout,swissup/askit,swissup/testimonials,swissup/sold-together,swissup/rich-snippets,swissup/reviewreminder,swissup/pro-labels,swissup/highlight,swissup/fblike,swissup/easytabs,swissup/easy-slide,swissup/easyflags,swissup/easycatalogimg,swissup/easybanner,swissup/attributepages,swissup/ajaxsearch,swissup/address-field-manager -v
➜ dep magento2:skeleton:summon --modules=Swissup_Core,Swissup_Askit
➜ dep magento2:skeleton:summon --packages=swissup/ajaxpro
~~~

### Remote server system requirements

1. Ssh server

2. Magento requirements
   - [Magento 1 technology stack requirements](https://docs.magento.com/m1/ce/user_guide/magento/system-requirements.html)
   - [Magento 2.2.x technology stack requirements](https://devdocs.magento.com/guides/v2.2/install-gde/system-requirements-tech.html)

3. Install Composer (optional)

   Download the [`composer.phar`](https://getcomposer.org/composer.phar) executable or use the installer.

    ```sh
    ➜  curl -sS https://getcomposer.org/installer | php
    ```

    > **Note:** If the above fails for some reason, you can download the installer
    > with `php` instead:

    ```sh
    ➜ php -r "readfile('https://getcomposer.org/installer');" | php
    ```

4. Download and install jq (optional)

    ```sh
    ➜  sudo yum install jq
    ```
    or
    ```sh
    ➜  git clone https://github.com/stedolan/jq.git
    ➜  cd jq
    ➜  autoreconf -i
    ➜  ./configure --disable-maintainer-mode
    ➜  make
    ➜  sudo make install
    ```
5. Download and install n98-magerun (optional)

    ```sh
    ➜  wget http://files.magerun.net/n98-magerun-latest.phar -O n98-magerun.phar
    ➜  mv n98-magerun.phar /usr/local/bin/n98-magerun
    ➜  chmod +x /usr/local/bin/n98-magerun
    ```
