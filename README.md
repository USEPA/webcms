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

4. Copy `settings.docker.local.php` to `settings.local.php`. Uncomment the last line (setting the default cache backend).

5. Install Drupal from config (or restore a backup).

6. Comment out the line you commented in step 4 - without it, you won't be caching in Redis.
