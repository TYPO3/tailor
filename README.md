# TYPO3 Extension Tailor

TYPO3 Extension Tailor is a CLI application to help you make your extension and support
your extension to `https://extensions.typo3.org`.

## Prerequisites

The TER REST API is based on a personal access token, which you should create at
`https://extensions.typo3.org/` after you've logged in.

Create a `.env` file for `tailor` in the project root folder or provide environment
variables through your system to this PHP script:

    TYPO3_API_TOKEN=your-token
    TYPO3_API_USERNAME=your-t3o-username
    TYPO3_API_PASSWORD=your-typo3-password

We've built the API token authentication for TER, so you do not have to provide a
username + password anymore. If you haven't created an API token yet,
you can use Username + password, but it is highly discouraged.

## Installation

Use it as a dev dependency via composer of your extension:

    composer req --dev typo3/tailor

## Usage

### Ensure your credentials are correct

    ./vendor/bin/tailor ter:authenticate

### Find available or compatible extensions on TER

    ./vendor/bin/tailor ter:find
    ./vendor/bin/tailor ter:find --typo3-version=9

### Show all details for a given extension

    ./vendor/bin/tailor ter:details my-extension-key

### Register a new extension key

    ./vendor/bin/tailor ter:register my-extension-key

### Publish a version of my extension to TER

    ./vendor/bin/tailor ter:publish my-extension 1.2.0 --path=/path/to/my-extension
    ./vendor/bin/tailor ter:publish my-extension 1.3.0 --artefact=/path/to/any-zip-file/any_zip_file_with_extensions.zip
    ./vendor/bin/tailor ter:publish my-extension 1.4.0 --artefact=https://github.com/my-name/my-extension/archive/1.4.0.zip

### Transfer the ownership of my extension to another TYPO3.org user account

    ./vendor/bin/tailor ter:transfer-ownership my-extension-key spoonerweb

### Abandon my own extension key

    ./vendor/bin/tailor abandon my-extension

## Integration into your CI pipeline

    # Step 1: Update the version in ext_emconf.php
    ./vendor/bin/tailor set-version 1.5.0
    # Step 2: Commit the changes and add a tag
    git commit -m "[RELEASE] A new version was published"
    git tag -a 1.5.0
    # Step 3: Push this to your remote repository
    git push origin --tags
    # Step 4: Push this version to TER
    ./vendor/bin/tailor ter:publish 1.5.0

## Author & License

Created by Benni Mack in 2020.