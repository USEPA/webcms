# First-Time Setup

Note: this has only been tested on ddev 1.19 and above.

1. Clone main branch of repo:

   ```
   git clone -b main git@github.com:USEPA/webcms.git
   ```

2. Then, start the project:

   ```
   cd services/drupal && ddev start
   ```

3. Next, create the S3 bucket for s3fs:

   ```
   ddev aws-setup
   ```

4. After that, please download the latest database (see https://forumone.atlassian.net/wiki/spaces/EPA/pages/1794637894/HOWTO+Import+D8+InnoDB+Cold+Backup) and put the .tar file in the `services/drupal/.ddev/db/` folder.  The filename isn't important; you will be prompted to select the DB you wish to import during the next step.

5. Import the database by running [`ddev import-db`](https://ddev.readthedocs.io/en/stable/users/usage/database-management/#database-imports)

   ```
   ddev import-db [--file=path/to/backup.sql.gz]
   ```

   Note that for very large dumps this may time out. Sometimes, DDEV lets the import process run in the background (verify this by watching the CPU and I/O activity of `docker stats`).

   If DDEV kills the import process, try connecting a MySQL command-line client directly to the container (use `ddev status` to see the forwarded MySQL port) and import that way.

6. Copy `cp .env.example .env`.

7. Install dependencies:

   ```
   ddev composer install
   ```

8. Install the requirements for the theme: `ddev gesso install`:

   ```
   ddev gesso install
   ```

9.  Building/watching the CSS and Pattern Lab:
    1. to build
      ```````
      ddev gesso build
      ```````
    2. to watch:
      ```````
      ddev gesso watch
      ```````

10. Install Drupal from config (or restore a backup).  You can install from config by running:

    **Note**: Do not run this command if starting from a new installation. This will wipe the database out, instead skip to #11.

   ```
   ddev drush si --existing-config
   ```

11. Ensure the latest configuration has been fully applied and clear cache:
   ```
   ddev drush deploy -y
   ```

12. Edit your `services/drupal/.env` file and change the line that reads `ENV_STATE=build` to read `ENV_STATE=run` -- without this change you will not make use of memcached caching.

13. To unblock the user run  `ddev drush user:unblock drupalwebcms-admin`

14. Access the app at https://epa.ddev.site

# Other READMEs

- Terraform configuration: see [infrastructure/terraform](infrastructure/terraform/README.md).
- Builds: see [.buildkite](.buildkite/README.md).

# Troubleshooting

### Elasticsearch
If you run into an error trying to use `elasticsearch`. Please run the following command:
```
ddev poweroff && docker volume rm ddev-epa-ddev_elasticsearch && ddev start
```

You will need to `re-index`.

# Helpful commands

Here is a list of helpful commands:
* `ddev gesso install` > Installs the node modules needed for `epa_core`.
* `ddev gesso build` > Builds the current assets for CSS & PatternLab.
* `ddev gesso watch` > Same thing as build but will watch for changes.
* `ddev ssh` > Goes into the web container.
* `ddev drush` > Runs any drush command.
* `ddev import-db` > Will import a specific database.
* `ddev export-db` > mExports a database with the current date.
* `ddev aws-setup` > Sets up the requirements to get drupal file system to work
* `ddev describe` or `ddev status` > Get a detailed description of a running DDEV project.
* `ddev phpmyadmin` > Launch a browser with PhpMyAdmin

# Disclaimer

The United States Environmental Protection Agency (EPA) GitHub project code is provided on an "as is" basis and the user assumes responsibility for its use.  EPA has relinquished control of the information and no longer has responsibility to protect the integrity , confidentiality, or availability of the information.  Any reference to specific commercial products, processes, or services by service mark, trademark, manufacturer, or otherwise, does not constitute or imply their endorsement, recommendation or favoring by EPA.  The EPA seal and logo shall not be used in any manner to imply endorsement of any commercial product or activity by EPA or the United States Government.
