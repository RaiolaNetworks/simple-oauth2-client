# Changelog

All notable changes to `oauth` will be documented in this file.

## v1.0.6 - 2024-10-22

### Temporary modifications to the configuration file variables.

A line has been added to solve a synchronization problem when recovering a value from the configuration file when executing migrations.

The variables in the configuration file are temporarily configured before finishing the process since the 'oauth' file is not required until the installation command process finishes.

## v1.0.5 - 2024-10-22

### Modified composer.json file.

Added v10.* in the "laravel/framework" requirement to support older versions.

## v1.0.4 - 2024-10-03

### All login modes have been moved to an Enum file.

An Enum file has been created to store all login modes to facilitate access to these modes in the application implementing the package and thus avoid the hardcoding or definitions of the modes.

## v1.0.2 - 2024-10-02

### Checking if OAuth user data exists.

Added a check if OAuth information exists in the OAuthController refresh function.

## Fixed an error in modifying variables in the configuration file - 2024-09-30

A new function has been added to the package installation command class that is responsible for modifying the value of the configuration file variables.

With this, the test responsible for testing said function has been created.

## Improved installation command performance - 2024-09-29

A new function has been added to the package installation command class that is responsible for modifying the value of the configuration file variables.

With this, the test responsible for testing said function has been created.
