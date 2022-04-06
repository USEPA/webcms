# First-Time Setup

1. First, start the project:

   ```
   cd services/drupal && ddev start
   ```

2. Next, create the S3 bucket for s3fs:

   ```
   ddev aws-setup
   ```

3. After that, please download the latest database and put it in the `services/drupal/.ddev/db/` folder.

4. Import the database by running: 

   ```   
   ddev epa-import 
   ```

5. After the import you will need to restart:

   ```   
   ddev poweroff && ddev start
   ```

6. Copy `cp .env.example .env`.

7. Copy environment settings:
   
   ```    
   cp web/sites/default/settings.local.env.php web/sites/default/settings.local.php
   ```

8. Install dependencies: 

   ```
   ddev composer install
   ```

9. Install the requirements for the theme: `ddev gesso install`:

   ```
   ddev gesso install
   ```
   
10. Building/watching the CSS and Pattern Lab:
    1. to build
      ```````
      ddev gesso build 
      ```````
    2. to watch:
      ```````
      ddev gesso build
      ```````
   
13. Watch the CSS and Pattern Lab:
   
   ```
   ddev gesso watch
   ```

14. Install Drupal from config (or restore a backup).  You can install from config by running:
   ```
   ddev drush si --existing-config
   ``` 

15. Ensure the latest configuration has been fully applied and clear cache: 
   ```
   ddev drush deploy -y
   ```

16. Edit your `services/drupal/.env` file and change the line that reads `ENV_STATE=build` to read `ENV_STATE=run` -- without this change you will not make use of Redis caching.

17. Access the app at https://epa-ddev.ddev.site

# Testing migrations

To load a D7 DB copy for testing:

```
gunzip < cleaned-d7-db.sql.gz | f1 drush sqlc --db-url mysql://web_d7:web_d7@mysql:3306/web_d7
```

# Other READMEs

- Terraform configuration: see [infrastructure/terraform](infrastructure/terraform/README.md).
- Builds: see [.buildkite](.buildkite/README.md).

# Troubleshooting

If you run into an error trying to use `elasticsearch`. Please run the following command:
```
ddev poweroff && docker volume rm ddev-epa-ddev_elasticsearch && ddev start
```

You will need to `re-index`.

# Disclaimer

The United States Environmental Protection Agency (EPA) GitHub project code is provided on an "as is" basis and the user assumes responsibility for its use.  EPA has relinquished control of the information and no longer has responsibility to protect the integrity , confidentiality, or availability of the information.  Any reference to specific commercial products, processes, or services by service mark, trademark, manufacturer, or otherwise, does not constitute or imply their endorsement, recommendation or favoring by EPA.  The EPA seal and logo shall not be used in any manner to imply endorsement of any commercial product or activity by EPA or the United States Government.
