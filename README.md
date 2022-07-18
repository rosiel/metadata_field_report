## Metadata Field Report


The Metadata Field Report module is a fork of the Field Report 
module by George Anderson. This module creates a report (nested
under the 'Field List' report) that lists all bundle types and 
their fields, along with field properties/settings. 

## Installation

Add this git repository to your drupal site's composer.json:

```
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/igorw/monolog"
        }
    ],
}
```

Use composer to install this module:

```bash
composer require drupal/metadata_field_report:dev-1.x
```

Use the Drupal GUI or Drush to install the module, then 
find the report at Reports > Field List > Metadata Field Report.

## Screenshot

<img width="1042" alt="Screen Shot 2022-07-17 at 4 18 59 PM" src="https://user-images.githubusercontent.com/1943338/179421494-16023f9e-fe83-45bc-9db1-71baa4e5bd6a.png">

Fields details listed include:
- machine name
- label
- description
- repeatable
- translatable
- target entity types (for entity reference fields)
- auto create (for entity reference fields)

You can also click "Download report" under each bundle 
type to download that bundle's info as a CSV.

This should make it easier to manage metadata in fields.

## Maintainers

Rosie Le Faive (rosiel)
https://github.com/rosiel
