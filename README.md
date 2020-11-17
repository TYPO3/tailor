# Tailor

Tailor is a CLI application to help you maintain your extensions.
Tailor talks with the TER REST API and enables you to register new
keys, update extension information and publish new versions to the
[extension repository][ter].

## Prerequisites

The TER REST API can be accessed providing a personal access token.
You can create such token either on [https://extensions.typo3.org/][ter]
after you've logged in, or directly using Tailor.

**Note:** To create an access token with Tailor, you have to add your
TYPO3.org credentials (see below). Even if it is possible to execute
all commands using the TYPO3.org credentials for authentication, it
is highly discouraged. That's why we have built token based
authentication for the [TER][ter].

Provide your credentials by either creating a `.env` file in the
project root folder or setting environment variables through your
system to this PHP script:

    TYPO3_API_TOKEN=<your-token>
    TYPO3_API_USERNAME=<your-t3o-username>
    TYPO3_API_PASSWORD=<your-t3o-password>

## Installation

Use Tailor as a dev dependency via composer of your extensions:

    composer req --dev typo3/tailor

## Usage

All commands provide the `-r, --raw` option. If set, the raw result
will be returned. This can be used for further processing e.g. by
using some JSON processor.

### Manage your personal access token

Use the `ter:token:create` command to create a new token:

    ./vendor/bin/tailor ter:token:create --name="token for my_extension" --extensions=my_extension

The result will look like this:

    Token type: bearer
    Access token: eyJ0eXAOiEJKV1QiLCJhb
    Refresh token: eyJ0eXMRxHRaF4hIVrEtu
    Expires in: 604800
    Scope: extension:read,extension:write
    Extensions: my_extension
    
As you can see, this will create an access token which is only
valid for the extension `my_extension`. The scopes are set to
`extension:read,extension:write` since this is the default if
option `--scope` is not provided. The same applies to the
expiration date which can be set with the option `--expires`.

If the token threatens to expire, refresh it with `ter:token:refresh`:

    ./vendor/bin/tailor ter:token:refresh eyJ0eXMRxHRaF4hIVrEtu
    
This will generate new access and refresh tokens with the same
options, initially set on creation.

To revoke an access token irretrievably, use `ter:token:revoke`:

    ./vendor/bin/tailor ter:token:revoke eyJ0eXAOiEJKV1QiLCJhb

### Register a new extension key

To register a new extension, use `ter:register` by providing
your desired extension key as argument:

    ./vendor/bin/tailor ter:register my_extension

This registers the key `my_extension` and returns following
confirmation:

    Key: my_extension
    Owner: your_username

### Publish a new version of an extension to TER

You can publish a new version of your extension using the
`ter:publish` command. Therefore, provide the extension key
and version number as arguments followed by the path to the
extension directory or an artefact (a zipped version of your
extension). The latter can be either local or a remote file.

Using `--path`:

    ./vendor/bin/tailor ter:publish my_extension 1.2.0 --path=/path/to/my_extension
    
Using a local `--artefact`:
    
    ./vendor/bin/tailor ter:publish my_extension 1.2.0 --artefact=/path/to/any-zip-file/my_extension.zip
    
Using a remote `--artefact`:

    ./vendor/bin/tailor ter:publish my_extension 1.2.0 --artefact=https://github.com/my-name/my_extension/archive/1.2.0.zip
    
**Note**: The REST API, just like the the [TER][ter], requires
an upload comment to be set. This can be achieved using the
`--comment` option. If not set, Tailor will automatically use
`Updated extension to <version>` as comment.

### Update extension meta information

You can update the extension meta information, such as the
composer name, or the associated tags with the `ter:update`
command.

To update the composer name:

    ./vendor/bin/tailor ter:update my_extension --composer=vender/my_extension
    
To update the tags:

    ./vendor/bin/tailor ter:update my_extension --tags=some-tag,another-tag
    
Please use `./vendor/bin/tailor ter:update -h` to see the full
list of available options.

**Note:** All options set with this command will overwrite the
existing data. Therefore, if you, for example, just want to add
another tag, you have to add the current ones along with the new
one. You can use `ter:details` to get the current state.

### Transfer the ownership of an extension to another user

It's possible to transfer one of your extensions to another user.
Therefore, use the `ter:transfer` command providing the extension
key to be transfered and the TYPO3.org username of the recipient.

Since you won't have any access to the extension afterwards, the
command asks for your confirmation before sending the order to
the REST API.

    ./vendor/bin/tailor ter:transfer my_extension some_user
    
This transfers the extension `my_extension`  to the user
`some_user` and returns following confirmation:

    Key: my_extension
    Owner: some_user    

**Note**: For automated workflows the confirmation can be
skipped with the ``-n, --no-interaction`` option.

### Delete / abandon an extension

You can easily delete / abandon extensions with Tailor using
the `ter:delete` command. This either removes the extension
entirely or just abandons it if the extension still has public
versions.

Since you won't have any access to the extension afterwards,
the command asks for your confirmation before sending the order
to the REST API.

    ./vendor/bin/tailor ter:delete my_extension
    
This will delete / abandon the extension `my_extension`.

**Note**: For automated workflows the confirmation can be
skipped with the ``-n, --no-interaction`` option.

### Find and filter extensions on TER

Tailor can't only be used for managing your extensions but
also to find others. Therefore, use `ter:find` by adding some
filters: 

    ./vendor/bin/tailor ter:find
    ./vendor/bin/tailor ter:find --typo3-version=9
    ./vendor/bin/tailor ter:find --typo3-author=some_user
    `
First command will find all public extensions. The second
and third one will only return extensions which match the
filter. In this case being compatible with TYPO3 version
`9` or owned by `some_user`.

To limit / paginate the result, you can use the options
`--page` and `--per_page`:

    ./vendor/bin/tailor ter:find --page=3 --per_page=20

#### Specific extension details

You can also request more details about a specific extension
using the `ter:details` command:

    ./vendor/bin/tailor ter:details some_ext_key
    
This will return details about the extension like the current
version, the author, some meta information and more. Similar
to the extension detail page on [extension.typo3.org][ter].

#### Specific extension version details

If you like to get details about a specific version of an
extension, `ter:version` can be used:

    ./vendor/bin/tailor ter:details some_ext_key 1.0.0
    
This will return details about version `1.0.0` of extension
`some_ext_key`.

**Overview of all available commands**

| Commands              | Arguments                         | Options                                                                                               | Description                                     |
| --------------------- | --------------------------------- | ----------------------------------------------------------------------------------------------------- | ----------------------------------------------- |
| ``ter:delete``        | ``extensionkey``                  |                                                                                                       | Delete an extension.                            |
| ``ter:details``       | ``extensionkey``                  |                                                                                                       | Fetch details about an extension.               |
| ``ter:find``          |                                   | ``--page``<br/>``--per-page``<br/>``--author``<br/>``--typo3-version``                                | Fetch a list of extensions from TER.            |
| ``ter:publish``       | ``extensionkey``<br/>``version``  | ``--path``<br/>``--artefact``<br/>``--comment``                                                       | Publishes a new version of an extension to TER. |
| ``ter:register``      | ``extensionkey``                  |                                                                                                       | Register a new extension key in TER.            |
| ``ter:token:create``  |                                   | ``--name``<br/>``--expires``<br/>``--scope``<br/>``--extensions``                                     | Request an access token for the TER.            |
| ``ter:token:refresh`` | ``token``                         |                                                                                                       | Refresh an access token for the TER.            |
| ``ter:token:revoke``  | ``token``                         |                                                                                                       | Revoke an access token for the TER.             |
| ``ter:transfer``      | ``extensionkey``<br/>``username`` |                                                                                                       | Transfer ownership of an extension key.         |
| ``ter:update``        | ``extensionkey``                  | ``--composer``<br/>``--issues``<br/>``--repository``<br/>``--manual``<br/>``--paypal``<br/>``--tags`` | Update extension meta information.              |
| ``ter:version``       | ``extensionkey``<br/>``version``  |                                                                                                       | Fetch details about an extension version.       |

**General options for all commands**

- ``-r, --raw`` Return result as raw object (e.g. json)
- ``-h, --help`` Display help message
- ``-q, --quiet`` Do not output any message
- ``-v, --version`` Display the CLI applications' version
- ``-n, --no-interaction`` Do not ask any interactive question
- ``--ansi`` Force ANSI output
- ``--no-ansi`` Disable ANSI output
- ``-v|vv|vvv, --verbose`` Increase the verbosity of messages: 1 for normal
output, 2 for more verbose output and 3 for debug

---
TODO:
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

---

## Author & License

Created by Benni Mack and Oliver Bartsch in 2020.

[ter]: https://extensions.typo3.org
