drupal-drux
===========

Dependency manager for Drush (Drupal bash/commandline)

The goal of this script is to sync enabled modules on different deployments (local, dev, staging, production).


## Installation

    cd ~/.drush
    git clone git@github.com:donquixote/drupal-drux.git
    drush cc drush


## Idea

The idea is that you create one or more "seed" modules which list the other enabled modules as dependencies in their info files.
There could be one seed module to share between all deployments. But there can be specific seed modules that are only for development, or only for your local installation.
The seed modules can be created with features, but you could also just create a basic custom module where you edit the *.info file manually.

An enabled module is considered "obsolete" with respect to one or more seed modules, if it could be safely disabled without disabling any of the seed modules.
The way to deal with obsolete modules is to either disable them, or to add them as a dependency.


## Commands

    drush drux-find-obsolete module1 module2 module3
    
Finds all enabled modules that are not direct or indirect dependencies of the specified seed modules.
In other words: It finds all modules that could be safely disabled without disabling any of the specified seed modules.


    drush drux-generate module1 module2 module3

Generate of dependencies[] = .. lines for "obsolete" modules as above, to copy + paste into a module info file.


    drush drux-list-dependencies
    
Finds disabled or missing modules that are required by any of the enabled modules on the site. E.g. if you pull from git, and one of your enabled modules suddenly gets new dependencies, this command will help you to identify the modules that need to be enabled.


    drush drux-enable-dependencies
    
Enables and potentially downloads modules that are required by enabled modules.

