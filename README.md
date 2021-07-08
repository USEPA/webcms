# First-Time Setup

1. First, start the project:

   ```
   f1 up
   ```

2. Next, create the S3 bucket for s3fs:

   ```
   f1 run aws s3 mb s3://drupal
   ```

3. Finally, allow anonymous access to the `public/` prefix of the S3 bucket: 

   ```
   f1 run aws s3api put-bucket-policy --bucket drupal --policy "$(cat services/minio/policy.json)"
   ```

4. Copy `services/drupal/.env.example` to `services/drupal/.env`.

5. Install dependencies: ```f1 composer install```

6. Build the CSS and Pattern Lab: `f1 run gesso gulp build`.

7. Install Drupal from config (or restore a backup).  You can install from config by running: ```f1 drush si --existing-config```

8. Ensure the latest configuration has been fully applied and clear cache: ```f1 drush cim -y; f1 drush cr``` 

9. Edit your `services/drupal/.env` file and change the line that reads `ENV_STATE=build` to read `ENV_STATE=run` -- without this change you will not make use of Redis caching.

10. Note the username/password generated!

11. Access the app at https://localhost:8443/

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
