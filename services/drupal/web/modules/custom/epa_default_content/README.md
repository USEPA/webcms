# EPA Default Content

This module creates placeholder content items for nodes and taxonomies on install with the goal of reducing redundant content entry during QA.

## Requirements

[Default Content](https://www.drupal.org/project/default_content)

## Build

This module was built using drush commands made available using the [default_content:1.x-dev](https://www.drupal.org/docs/8/modules/default-content-for-d8/overview) which also depends on Drupal 8 core's hal and serialization modules.

### Setup
1. After installing Default Content, you will create a custom module with a .info.yml file and a content directory.
2. Manually create your default content or perhaps use a tool like [Devel Generate](https://www.drupal.org/project/devel).
3. Export content using `drush dcer`, which exports the content and any referenced content.

    Example for a single node.
    ```shell
    drush dcer node 1 --folder=[path/to/custom_module/content]
    ```

    PowerShell example for multiple nodes assuming nodes 1 through 24 have been created.
    ```shell
    ForEach ( $id in 1..24 ) { drush dcer node $id --folder=path/to/custom_module/content }
    ```

    PowerShell example for multiple taxonomies using a file where each term id is on a new line.
    ```shell
    ForEach ( $line in [System.IO.File]::ReadLines("/path/to/file") ) { drush dcer taxonomy_term $line --folder=path/to/custom_module/content }
    ```

4. Install the module with a new site making sure the Default Content module has already been installed.

### Notes

1. When exporting content with references, users will be created. You may want to delete the users from the export if they already exist within the site where you install the custom module.
2. There are a number of shortcomings.
    - Relies on command line tools to export content.
    - Has [one method](https://www.drupal.org/docs/8/modules/default-content-for-d8/overview#s-default-content-export-module) for handling multiple files which requires knowing the UUID for the content.

## Installation

1. Copy the epa_default_content directory to modules/custom/.
2. Login as an administrator. Enable the module in "Administer" -> "Modules".


## TODO

- [ ] Add specific core dependencies.
