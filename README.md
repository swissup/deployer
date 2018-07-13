# Deployer
Deployment tool

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
➜ git clone git@github.com:swissup/deployer.git
➜ cd deployer
```
### Usage

#### List releasses

~~~
➜ dep releases:list 
➜ dep releases:list -vv
~~~

#### Clear all

~~~
➜ dep releases:cleanup:all
~~~

#### Test magento 1 deploy
~~~
➜ dep magento:create --packages=tm/ajax-pro:\*,tm/ajax-layered-navigation:\*,tm/ajax-search:\*,tm/ask-it:\*,tm/easy-banner:\*,tm/helpdesk:\*,tm/navigation-pro:\*,tm/cache:\*,tm/highlight:\*,tm/pro-labels:\*,tm/review-reminder:\*,tm/sold-together:\*

➜ dep magento2:releases:list
~~~


#### Test magento 2 deploying
~~~
➜ dep magento2:create --packages=swissup/ajaxpro,swissup/ajaxlayerednavigation,swissup/firecheckout,swissup/askit,swissup/testimonials,swissup/sold-together,swissup/rich-snippets,swissup/reviewreminder,swissup/pro-labels,swissup/highlight,swissup/fblike,swissup/easytabs,swissup/easy-slide,swissup/easyflags,swissup/easycatalogimg,swissup/easybanner,swissup/attributepages,swissup/ajaxsearch,swissup/address-field-manager -v

➜ dep magento2:releases:list 

➜ dep magento2:skeleton:create --packages=swissup/ajaxpro,swissup/ajaxlayerednavigation,swissup/firecheckout,swissup/askit,swissup/testimonials,swissup/sold-together,swissup/rich-snippets,swissup/reviewreminder,swissup/pro-labels,swissup/highlight,swissup/fblike,swissup/easytabs,swissup/easy-slide,swissup/easyflags,swissup/easycatalogimg,swissup/easybanner,swissup/attributepages,swissup/ajaxsearch,swissup/address-field-manager -v
➜ dep magento2:skeleton:summon --modules=Swissup_Core,Swissup_Askit
➜ dep magento2:skeleton:summon --packages=swissup/ajaxpro
~~~

### On Remote (deployment) machine
1. Install Composer
    Download the [`composer.phar`](https://getcomposer.org/composer.phar) executable or use the installer.

    ```sh
    ➜  curl -sS https://getcomposer.org/installer | php
    ```

    > **Note:** If the above fails for some reason, you can download the installer
    > with `php` instead:

    ```sh
    ➜ php -r "readfile('https://getcomposer.org/installer');" | php
    ```

2. Download and install jq

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
3. Download and install n98-magerun     

    ```sh
    ➜  wget http://files.magerun.net/n98-magerun-latest.phar -O n98-magerun.phar
    ➜  mv n98-magerun.phar /usr/local/bin/n98-magerun
    ➜  chmod +x /usr/local/bin/n98-magerun
    ```
