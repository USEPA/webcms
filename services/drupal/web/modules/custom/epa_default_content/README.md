# EPA Default Content

This module creates placeholder content items for nodes and taxonomies on install with the goal of reducing redundant content entry during QA.

## Requirements

[Default Content](https://www.drupal.org/project/default_content)

## Build

This module was built using drush commands made available by [default_content:1.x-dev](https://www.drupal.org/docs/8/modules/default-content-for-d8/overview), which also depends on Drupal 8 core's hal and serialization modules.

### Setup
1. After installing Default Content, you will create a custom module with a .info.yml file and a content directory.
2. Manually create your default content or perhaps use a tool like [Devel Generate](https://www.drupal.org/project/devel).
3. Export content using `drush dcer`, which exports the content and any referenced entity (i.e. when exporting a node that contains a file field, both the node and the file entity will be exported).

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

4. Install the module with a new site making sure the Default Content module has already been enabled.

### Notes

1. When exporting an entity with its referenced entities, a user will also be exported. You will want to delete the users from the export if they already exist within the site where this custom module will be enabled.
2. There are a number of shortcomings.
    - Relies on command line tools to export content.
    - Has [one method](https://www.drupal.org/docs/8/modules/default-content-for-d8/overview#s-default-content-export-module) for handling multiple files, which requires knowing the UUID for each entity.

## Installation

1. Copy the epa_default_content directory to modules/custom/.
2. Login as an administrator. Enable the module in "Administer" -> "Modules".


## TODO

- [ ] Add specific core dependencies.
