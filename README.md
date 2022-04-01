# First-Time Setup

1. First, start the project:

   ```
   cd services/drupal && ddev start
   ```

2. Next, create the S3 bucket for s3fs:

   ```
   ddev aws-setup
   ```

3. After that, please download the latest database and put it in the `.ddev/db/` folder.

4. Import the database by running: 

   ```   
   ddev epa-import 
   ```

7. After the import you will need to restart:

   ```   
   ddev poweroff && ddev start 
   ```

--- 4. Copy `services/drupal/.env.example` to `services/drupal/.env`.

5. Install dependencies: ```ddev composer install```

6. Install the requirements for the theme: `ddev gesso install`.

7. Build the CSS and Pattern Lab: `ddev gesso build`. If you want to run `watch` please run `ddev gesso watch`

9. Install Drupal from config (or restore a backup).  You can install from config by running: ```ddev drush si --existing-config```

10. Ensure the latest configuration has been fully applied and clear cache: ```ddev drush cim -y; ddev drush cr``` 

---- 11. Edit your `services/drupal/.env` file and change the line that reads `ENV_STATE=build` to read `ENV_STATE=run` -- without this change you will not make use of Redis caching.

---- 12. Note the username/password generated!

13. Access the app at https://epa-ddev.ddev.site

# Testing migrations

To load a D7 DB copy for testing:

```
gunzip < cleaned-d7-db.sql.gz | f1 drush sqlc --db-url mysql://web_d7:web_d7@mysql:3306/web_d7
```

# Other READMEs

- Terraform configuration: see [infrastructure/terraform](infrastructure/terraform/README.md).
- Builds: see [.buildkite](.buildkite/README.md).

# Disclaimer

The United States Environmental Protection Agency (EPA) GitHub project code is provided on an "as is" basis and the user assumes responsibility for its use.  EPA has relinquished control of the information and no longer has responsibility to protect the integrity , confidentiality, or availability of the information.  Any reference to specific commercial products, processes, or services by service mark, trademark, manufacturer, or otherwise, does not constitute or imply their endorsement, recommendation or favoring by EPA.  The EPA seal and logo shall not be used in any manner to imply endorsement of any commercial product or activity by EPA or the United States Government.
