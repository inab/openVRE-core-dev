## OpenVRE Development Setup Guide

## Pre-requisites

- **Docker Engine - Community** (Version: 26.1.0)
- **Docker Compose** (Version: v2.26.1)

## Cloning the Repository

Clone the OpenVRE core development repository using the following command:

```sh
 git clone https://github.com/inab/openVRE-core-dev.git --branch techton2025
 git clone https://github.com/mapoferri/vre_dockerized_tool_techthon.git
```

Navigate into the cloned directory:

```sh
cd openVRE-core-dev
```

## Pulling Required Docker Images

Run the following commands to pull the necessary Docker images from [GitHub Container Registry](https://github.com/mapoferri?tab=packages) and from [Docker Hub](https://hub.docker.com/repositories/mapoferri):

```sh
docker pull ghcr.io/mapoferri/sgecore:latest
docker pull ghcr.io/mapoferri/vault:1.13.3
docker pull ghcr.io/mapoferri/mongo:4.4
docker pull ghcr.io/mapoferri/quay.io/keycloak/keycloak:15.0.2
docker pull ghcr.io/mapoferri/postgres:latest
docker pull mapoferri/paraview_image2
docker pull mapoferri/techton-seq-tool
docker pull ghcr.io/mapoferri/front_end:latest
```

## Setup configuration files (Techthon Session 1)

First thing, is to create and configure your own  `.env` file:
```
cd openVRE-core-dev
cp .env.sample .env
```

Edit the new `.env` file and adapt it to your own environment. At the moment, the default values would work in most of the systems, just make sure to setup the hostname depending on the installation environment. Examples:
- FQDN_HOST:
    - For local development: `$FQDN_HOST=localhost`
    - If you have a domain: `$FQDN_HOST=myapp.example.com`
    - If using a WSL or internal IP for access: `$FQDN_HOST=192.168.x.x`
- UID: Identifier of the host user running the containers (`id`)
- GID: Identifier of the host group running the containers (non-privileged users should belong to `docker`group)
- DOCKER_GROUP: Identifier of the `Docker` group. 

The *frontend* component uses its own set of configuration files. Make sure to create and update the default values according to your needs:

```bash
cd front_end/openVRE/config

cp globals.inc.php.sample globals.inc.php
cp mail.conf.sample mail.conf
cp mongo.conf.sample mongo.conf 
cp oauth2.conf.sample oauth2.conf
cp oauth2_admin.conf.sample oauth2_admin.conf

```

## Import container images

#### Option 1: pull images
You can user already build images from [GitHub Container Registry](https://github.com/mapoferri?tab=packages). 

#### Option 2: build images 
Return to the `openVRE-core-dev` folder and check the `docker-compose.yml` file before building the containers. Two docker images are going to be build according to it:  `sgecore` and `front_end`. The task could take a while...
```
cd openVRE-core-dev/
docker compose build
```
Check the new images:
```
docker images
```

## Start the services  (Techthon Session 1)

Validate the `docker-compose.yml` file before creating and starting them with the following command: 
```
docker-compose --profile "local_auth" --profile populate_DB up -d 
```
and check the status of the resulting containers
```
docker ps -a
```

## Apply manual SGE configuration (Techthon Session 1):

### sgecore username:

Before initialiting the configuration for the SGE to recognized jobs sent from the front_end, if the user is not sure of the hostname for the front_end docker, please use the command to retrieve it and use it. 
```
docker inspect front_end | grep -i Hostname 
```

Change minimal UID in SGE master configuration to allow job submission from web apps:

```
docker exec -it sgecore /bin/bash
qconf -as ${FRONT_END_HOSTNAME}

qconf -mconf # change UID from 1000 to 33
```

### sgecore docker usage permission

```
groupmod -g 120 docker   #or to the respective docker group of the system, which could be obtained by running bash command: getent group docker
usermod -aG docker application


chown root:docker /var/run/docker.sock
chmod 660 /var/run/docker.sock

```

## Set Keycloak Configuration (Techthon Session 1):

Check match user and secret with keycloak config.
Keycloak to front-end should be allowes via iptables in some systems, so run the command locally on the machine:

```
sudo iptables -I INPUT -s {keycloak internal IP} -p tcp --dport 8080 -j ACCEPT
```

The Keycloak configuration is following the oauth2.conf, where the User and the Secret should be stored.
If the secret is unknown or uncertain, access the Admin console to access and retrieve the Secret and store it in the oauth2.conf file, so the Keycloak server would be accessible through the VRE.

#### How to do it: 

1. Once the docker is up, access through the web-page at the link: *http(s)://{$FQDN_HOST}/auth/admin* ;

2. Access the Admin console with the Admin credentials stored in the oauth2_admin.conf;

3. Open the *Clients* section;

4. Open the *open-vre* Client ID section;

5. Go over the *Credentials* section, where you would find the Client Id and the Secret;

6. On your own VRE configuration file, *openVRE/config/oauth2.conf*, update the file with the credentials aforementioned. 

The access to the Realm is complete, you should be able to access and register new user on your local Keycloak server. 
Before closing the session, check the Vault configuration, since it needs a KeyCloak Client to be set up.



## Set Vault Configuration (Techthon Session 1):


### Keycloak Configuration for HashiCorp Vault Integration

This guide explains how to configure a Keycloak client to enable interaction between HashiCorp Vault and Keycloak for authentication and authorization using JWT tokens.

#### Step 1: Configure Your Keycloak Client

1. **Log in to the Keycloak admin console** through *http://localhost:9099/auth* and navigate to your realm.

2. **Locate your existing client**, in this case is the *open-vre* one. 

3. In the client settings, configure the following:

   - **Root URL**:
     ```
     https://$FQDN_HOST/
     ```
     Replace `$FQDN_HOST` with your fully qualified domain name (e.g., `vre.disc4all.eu`).

   - **Valid Redirect URIs**:
     ```
     https://$FQDN_HOST/*
     ```
     Additionally:
     ```
     http://$FQDN_HOST/ui/vault/auth/oidc/oidc/callback
     ```

     > Ensure `$FQDN_HOST` is replaced with the correct host name for your deployment (e.g., `vre.disc4all.eu`).

4. **Save the changes** to the client configuration to ensure the URIs are authorized by Keycloak.

#### Step 2: Create a New Client for Vault

To enable Vault to authenticate and authorize users via Keycloak, create a new dedicated Keycloak client for Vault.

1. **Go to the Clients section** in the Keycloak admin console.

2. **Click on the "Create" button** (on the right side of the clients table) to create a new client.

3. **Set the Client ID** to: *open-vre-vault*, with the same root Url as *open-vre* client. 

4. **Configure the following for the new client**:

- **Root URL**:
  ```
  https://$FQDN_HOST/
  ```

- **Valid Redirect URIs**:
  ```
  https://$FQDN_HOST/*
  ```
  Additionally:
  ```
  http://$FQDN_HOST/ui/vault/auth/oidc/oidc/callback
  ```

> Replace `$FQDN_HOST` with your domain (e.g., `vre.disc4all.eu`).

5. **Save the new client configuration**.


With the above configuration, Vault will be able to interact with Keycloak for OpenID Connect (OIDC) authentication, once it is configured manually on the Vault.
Before interacting with the Vault Server container, for the next configuration step, is necessary to retrieve the *JWKS validating public key*, directly from the Keycloak Realm.
Accessing the Admin Keycloak Interface through these steps :

1. Access the Vault-Server info using this command: 
```
curl http://$FDQN_HOST/auth/realms/open-vre/protocol/openid-connect/certs
```
;

2. Copy the results so to copy the *n* and the *e* values from the response array;

3. Redirect in the *vault/* dir;

4. Substitute the vaules you had saved in the *pem.py* script;

5. Launch the *pem.py* script: 

```
python3 pem.py >> public-key.pem
mv public-key.pem config/
```

6. Make sure that the key was saved in the *vault/config/* dir.


### Vault GUI unseal

First time Vault is up, it is possible to access and explore the Vault via the UI.

You can connect to it via *http://hostname:8200/ui/vault/*.
There you would be able to set the number of keys you want to produce and to use to unseal the Vault.

**Save the keys!**

Once you proceed on the unseal process, and the Status of the Vault turns to green, from the Admin page it would be possible to establish some configuration. 
For example, setting up some policies. 

Click on the *Policies* section.
Here with the button *Create ACL Policy*, we will add two policies: **OIDC** and **JWT**, for the Vault to communicate with the Keycloak local server. 

The policies are gonna be named *jwt-role-demo*: 
```
path "auth/jwt/role/demo" {
  capabilities = ["create", "read", "update", "delete"]
}

path "secret/*" {
  capabilities = ["create", "read", "update", "delete"]
}

path "auth/token/lookup-self" {
  capabilities = ["read"]
}
path "auth/token/renew-self" {
  capabilities = ["update"]
}
path "auth/token/revoke-self" {
  capabilities = ["update"]
}
```

and *oidc-role-myrole*:

```
path "auth/oidc/role/myrole" {
  capabilities = ["create", "read", "update", "delete"]
}

path "secret/mysecret" {
  capabilities = ["create", "read", "update", "delete"]
}
```

Rest of the configuration could be done manually. 



### Vault manual unseal

First time Vault up, access the containers in *interactive* mode, to execute the init and save elsewhere the 'Unseal keys' just generated:

```
docker exec -ti vault-server vault operator init 

```
On every Vault restart, use the following command to unseal the vault using 3 out of the 5 Unseal Keys generated during the init. 

```
docker exec -ti vault-server vault operator unseal SECRET_KEY1
docker exec -ti vault-server vault operator unseal SECRET_KEY2
docker exec -ti vault-server vault operator unseal SECRET_KEY3
```
### Vault manual setup

Considering an external JWT Authorization Token service as a middle identification layer to access the Vault and its secrets, it has to be properly registered.
Here are the command to follow to instatiate a JWT Authorization service for Keycloak: 

```
docker exec -ti vault-server /bin/sh
vault login # with ${Intial Root Token}

vault auth enable jwt
vault auth enable oidc

#Policy, if not done by UI
cd vault/config
vault policy write jwt-role-demo jwt-role-demo.hcl
vault policy write oidc-role-myrole oidc-role-myrole-policy.hcl

#Role
vault write auth/oidc/role/myrole allowed_redirect_uris="[http://$HOSTNAME/ui/vault/auth/oidc/oidc/callback, http://localhost:8250/oidc/callback]" user_claim="sub" #Hostname can coincide with $FQDN_HOST
vault write auth/jwt/role/demo bound_audiences="account" allowed_redirect_uris="http://localhost:8250/oidc/callback" user_claim="sub" policies=jwt-role-demo role_type=jwt ttl=1h
vault write auth/jwt/role/demo role_type="jwt"
#vault write auth/jwt/role/demo bound_audiences="account"

#Configuration
#The public key can be retrieved directly from the Keycloak Realm (from the JWKS endpoint)
vault write auth/jwt/config default_role=demo bound_issuer="https://$KEYCLOAK_REALM" jwt_validation_pubkeys=@public-key.pem bound_audiences="account"

#Secrets
vault secrets enable -path=secret/mysecret kv-v2


```

## Set Graphic configuration (Techthon Session 1):


Whenever you want your own version of the VRE, some steps have to be followed:

1. Step 1: Update `htmlib/top.inc.php`

### 1.1 Modify the Logo

To update the logo in your project, follow these steps:

1. Open `htmlib/top.inc.php` in your code editor.
2. Locate the `<img>` tag that displays the logo.
3. Update the `src` attribute to point to your project’s custom logo file.
4. Adjust the inline style or class for the logo's size and layout as needed.

#### Example Update for the Logo:
```php
<img src="assets/your_project_path/your-logo.png" alt="Project Logo" class="logo-default" style="width:45%;"/>
```
Replace *assets/your_project_path/your-logo.png* with the path to your logo file. Modify *style="width:45%;"* or add appropriate CSS classes to adjust the logo's size and layout.

2. Step 2: Modify Your Theme in `assets/layouts/layout/css`

To further customize the look and feel of your application, you can modify the theme-related CSS files located in **/openVRE-core-dev/front_end/openVRE/public/assets/layouts/layout/css**.

### 2.1 Update Global Styles and Theme Settings

In the `css` directory, you’ll find the CSS files that define the global styles for the entire application. You can adjust the global styles such as colors, fonts, buttons, headers, and backgrounds. By modifying these settings, you can better align the look of the application with your project's branding.
you can add new CSS rules in these CSS files, or create a new custom stylesheet for your project (*using* **custom.css** file).


#### Example Customizations:

- **Change Primary Colors:**

To change the primary color for buttons or any other elements, find the relevant CSS class and modify its color settings. For example, to change the primary button color:

```css
.btn-primary {
    background-color: #yourPrimaryColor; /* Replace with your color */
    border-color: #yourPrimaryColor;     /* Replace with your color */
}
```

- **Adjust Header Styles:**

You can modify styles for the header or navigation bar to match your branding. For example, changing the background color of the navbar:

```css
.navbar {
    background-color: #yourNavbarColor;  /* Replace with your navbar color */
}
```

- **Customize Button Styles:**

If you want to change the appearance of buttons throughout the site, locate the button-related CSS classes and update them:

```css
.btn {
    background-color: #yourButtonColor;  /* Replace with your color */
    color: #yourTextColor;               /* Replace with your text color */
}
```







## Troubleshotting ⚠️

1. **Frontend is not being build because of a Mongo dependency**
    -  When building  `front_end` container: MongoDB occasionally moves or updates their GPG key. You can to replace the original `RUN` line in `frontend/Dockerfile` by an equivalent line using another URL instead:

```
RUN curl -fsSL https://pgp.mongodb.com/server-4.2.asc | apt-key add -
```

2. **Authentication failes because token expiration**
    - When connecting to an external Keycloak service, make sure that both systems have the right date and time. 
```
#Updating timezone
sudo timedatectl set-timezone Europe/Madrid
```

3. **Error response from daemon: path /home/jmfernandez/TEMP/openVRE-core-dev/volumes/shared_data is mounted on / but it is not a shared mount**
    - You can solve this problem running this command:
    ```
    sudo mount --make-shared /
    ```

    - Another solution you can use (can change based on the operating system), is commenting or deleting the line **:rshared** in the *docker-compose.yml* **sgecore** Volumes section. 

4. **Data is not accessible**
    - After setting up the *docker-compose up* command, and trying to connect to the *front_end* via web, the error **Data is not accessible** comes out. In your system, you will have to change permissions of the *openVRE-core-dev
  /volumes/* dir permission:
    ```
    chmod 777 volumes/shared/data
    ```
5. **$FQDN_HOST port 272017 already in use**
    - After setting up the *docker-compose up* command, from the *front_end* container errors (coming from running *docker logs front_end*) and this is the error appearing, it would be needed to change the value for port configuration for *$MONGO_PORT* in the *.env* file, as well as in *openVRE-core-dev
  /front_end/openVRE/config/mongo.conf* corresponding port value.
