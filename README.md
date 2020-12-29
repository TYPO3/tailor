# Tailor

![Tests](https://github.com/TYPO3/tailor/workflows/tests/badge.svg)

Tailor is a CLI application to help you maintain your extensions.
Tailor talks with the [TER REST API][rest-api] and enables you to
register new keys, update extension information and publish new
versions to the [extension repository][ter].

## Prerequisites

The [TER REST API][rest-api] can be accessed providing a personal
access token. You can create such token either on
[https://extensions.typo3.org/][ter] after you've logged in, or
directly using Tailor.

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
    
**Note**: For an overview of all available environment variables,
have a look at the `.env.dist` file.
    
**Note**: You can also add environment variables directly on
executing a command. This overrides any variable, defined in
the `.env` file.

Example:

    TYPO3_API_TOKEN="someToken" TYPO3_EXTENSION_KEY="ext_key" bin/tailor ter:details
    
This will display the extension details for extension `ext_key` if
`someToken` is valid (not expired/revoked and having at least the
`extension:read` scope assigned).

## Installation

Use Tailor as a dev dependency via composer of your extensions:

    composer req --dev typo3/tailor

## Usage

All commands, requesting the TER API, provide the `-r, --raw`
option. If set, the raw result will be returned. This can be
used for further processing e.g. by using some JSON processor.

Most of the commands require an extension key to work with.
However, since Tailor can be required as a dev dependency into
any single TYPO3 extension project it's also possible to define
the extension key as an environment variable with
`TYPO3_EXTENSION_KEY` globally. So you don't have to add it on
each command exceution manually.

**Note:** If no extension key is defined, neither as environment
variable nor as argument, commands which require an extension
key to be set, will throw an exception.

**Tip:** Definition of an extension key as argument precedes the
value from the environment variable. This means, even if you have
an extension key defined globally, you can still run all commands
for different extensions by adding them as argument to the command.

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
    
### Update the version of your extension in `ext_emconf.php`

Prior to publishing a new version, you have to update the
version in your extensions `ext_emconf.php` file. This can
be done using the `set-version` command.

    ./vendor/bin/tailor set-version 1.2.0
    
**Note**: It's also possible to use the `--path` option to
specify the location of your extension. If not given, your
current working directory is search for the `ext_emconf.php`
file.

**Note**: The version will only be updated if already present
in your `ext_emconf.php`. It won't be added by this command.

### Publish a new version of an extension to TER

You can publish a new version of your extension using the
`ter:publish` command. Therefore, provide the extension key
and version number as arguments followed by the path to the
extension directory or an artefact (a zipped version of your
extension). The latter can be either local or a remote file.

Using `--path`:

    ./vendor/bin/tailor ter:publish 1.2.0 my_extension --path=/path/to/my_extension
    
Using a local `--artefact`:
    
    ./vendor/bin/tailor ter:publish 1.2.0 my_extension --artefact=/path/to/any-zip-file/my_extension.zip
    
Using a remote `--artefact`:

    ./vendor/bin/tailor ter:publish 1.2.0 my_extension --artefact=https://github.com/my-name/my_extension/archive/1.2.0.zip
    
Using the root direcotry:

    ./vendor/bin/tailor ter:publish 1.2.0 my_extension
    
If the extension key is defined as environment variable,
it can also be skipped. So using the current root directory
the whole command simplifies to:

    ./vendor/bin/tailor ter:publish 1.2.0
    
**Important**: A couple of directories and files are excluded
from packaging by default. You can find the configuration in
`conf/ExcludeFromPackaging.php`. If you like, you can also
use a custom configuration. Just add the path to your custom
configuration file to the environment variable
`TYPO3_EXCLUDE_FROM_PACKAGING`. This file must return an
`array` with the keys `directories` and `files` on root level.
    
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

    ./vendor/bin/tailor ter:transfer some_user my_extension
    
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

    ./vendor/bin/tailor ter:details my_extension
    
This will return details about the extension `my_extension`
like the current version, the author, some meta information
and more. Similar to the extension detail page on
[extension.typo3.org][ter].

#### Specific extension version details

If you like to get details about a specific version of an
extension, `ter:version` can be used:

    ./vendor/bin/tailor ter:version 1.0.0 my_extension
    
This will return details about version `1.0.0` of extension
`my_extension`.

#### Details for all versions of an extension

You can also get the details for all versions of an extension
with `ter:versions`:

    ./vendor/bin/tailor ter:versions my_extension
    
This will return the details for all version of the extension
`my_extension`.

**Overview of all available commands**

| Commands              | Arguments                         | Options                                                                                               | Description                                     |
| --------------------- | --------------------------------- | ----------------------------------------------------------------------------------------------------- | ----------------------------------------------- |
| ``set-version``       | ``version``                       | ``--path``                                                                                            | Update the version in ``ext_emconf.php``        |
| ``ter:delete``        | ``extensionkey``                  |                                                                                                       | Delete an extension.                            |
| ``ter:details``       | ``extensionkey``                  |                                                                                                       | Fetch details about an extension.               |
| ``ter:find``          |                                   | ``--page``<br/>``--per-page``<br/>``--author``<br/>``--typo3-version``                                | Fetch a list of extensions from TER.            |
| ``ter:publish``       | ``version``<br/>``extensionkey``  | ``--path``<br/>``--artefact``<br/>``--comment``                                                       | Publishes a new version of an extension to TER. |
| ``ter:register``      | ``extensionkey``                  |                                                                                                       | Register a new extension key in TER.            |
| ``ter:token:create``  |                                   | ``--name``<br/>``--expires``<br/>``--scope``<br/>``--extensions``                                     | Request an access token for the TER.            |
| ``ter:token:refresh`` | ``token``                         |                                                                                                       | Refresh an access token for the TER.            |
| ``ter:token:revoke``  | ``token``                         |                                                                                                       | Revoke an access token for the TER.             |
| ``ter:transfer``      | ``username``<br/>``extensionkey`` |                                                                                                       | Transfer ownership of an extension key.         |
| ``ter:update``        | ``extensionkey``                  | ``--composer``<br/>``--issues``<br/>``--repository``<br/>``--manual``<br/>``--paypal``<br/>``--tags`` | Update extension meta information.              |
| ``ter:version``       | ``version``<br/>``extensionkey``  |                                                                                                       | Fetch details about an extension version.       |
| ``ter:versions``      | ``extensionkey``                  |                                                                                                       | Fetch details for all versions of an extension. |

**General options for all commands**

- ``-r, --raw`` Return result as raw object (e.g. json) - Only for 
commands, requesting the TER API
- ``-h, --help`` Display help message
- ``-q, --quiet`` Do not output any message
- ``-v, --version`` Display the CLI applications' version
- ``-n, --no-interaction`` Do not ask any interactive question
- ``-v|vv|vvv, --verbose`` Increase the verbosity of messages:
1 for normal output, 2 for more verbose output and 3 for debug
- ``--ansi`` Force ANSI output
- ``--no-ansi`` Disable ANSI output

## Publish a new version using tailor locally

**Step 1: Update the version in ext_emconf.php**

    ./vendor/bin/tailor set-version 1.5.0

**Step 2: Commit the changes and add a tag**
 
    git commit -am "[RELEASE] A new version was published"
    git tag -a 1.5.0

**Step 3: Push this to your remote repository**

    git push origin --tags

**Step 4: Push this version to TER**

    ./vendor/bin/tailor ter:publish 1.5.0
    
**Note:** Both `set-version` and `ter:publish` provide options
to specify the location of your extension. If, like in the example
above, non is set, Tailor automatically uses your current working
directory.

## Publish a new version using your CI

You can also integrate tailor into you GitHub workflow respectively
your GitLab pipline. Therefore, **Step 1**, **Step 2** and **Step 3**
from the above example are the same. **Step 4** could then be
done by your integration.

Please have a look at the following examples describing how
such integration could look like for GitHub workflows and
GitLab pipelines. 

### Github workflow

The workflow will only be executed when pushing a new tag.
This can either be done using **Step 3** from above example
or by creating a new GitHub release which will also add a
new tag.

The workflow furthermore requires the GitHub secrets
`TYPO3_EXTENSION_KEY` and `TYPO3_API_TOKEN` to be set.

The version is automatically fetched from the tag and
validated to match the required pattern.

The commit message from **Step 2** is used as the release
comment. If it's empty, a static text will be used.

To see the following workflow in action, please have a
look at the [tailor_ext][tailor-ext] example extension.


```yaml
name: publish
on:
  push:
    tag:
jobs:
  publish:
    name: Publish new version to TER
    if: startsWith(github.ref, 'refs/tags/')
    runs-on: ubuntu-20.04
    env:
      TYPO3_EXTENSION_KEY: ${{ secrets.TYPO3_EXTENSION_KEY }}
      TYPO3_API_TOKEN: ${{ secrets.TYPO3_API_TOKEN }}
    steps:
      - name: Checkout repository
        uses: actions/checkout@v2

      - name: Check tag
        run: |
          if ! [[ ${{ github.ref }} =~ ^refs/tags/[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}$ ]]; then
            exit 1
          fi

      - name: Get version
        id: get-version
        run: echo ::set-output name=version::${GITHUB_REF/refs\/tags\//}

      - name: Get comment
        id: get-comment
        run: |
          readonly local comment=$(git tag -n10 -l ${{ steps.get-version.outputs.version }} | sed "s/^[0-9.]*[ ]*//g")

          if [[ -z "${comment// }" ]]; then
            echo ::set-output name=comment::Released version ${{ steps.get-version.outputs.version }} of ${{ env.TYPO3_EXTENSION_KEY }}
          else
            echo ::set-output name=comment::$comment
          fi

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 7.4
          extensions: intl, mbstring, json, zip, curl
          tools: composer:v2

      - name: Install tailor
        run: composer global require typo3/tailor --prefer-dist --no-progress --no-suggest

      - name: Publish to TER
        run: php ~/.composer/vendor/bin/tailor ter:publish --comment "${{ steps.get-comment.outputs.comment }}" ${{ steps.get-version.outputs.version }}
```

**Note**: If you're using tags with a leading `v` the above example needs to be adjusted.

1. The regular expression in step **Check tag** should be:

```
^refs/tags/v[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}$
```

2. The output format in step **Get version** should be:

```bash
${GITHUB_REF#refs/tags/v}
```

3. The variable declaration in step **Get comment** should be:

```bash
$(git tag -n10 -l v${{ steps.get-version.outputs.version }} | sed "s/^v[0-9.]*[ ]*//g")
```

### GitLab pipeline

The job will only be executed when pushing a new tag.
The upload comment is taken from the message in the tag.

The job furthermore requires the GitLab variables
`TYPO3_EXTENSION_KEY` and `TYPO3_API_TOKEN` to be set.

The variable `CI_COMMIT_TAG` is set by GitLab automatically.

```yaml
"Publish new version to TER":
  stage: release
  image: composer:2
  only:
    - tags
  before_script:
    - composer global require typo3/tailor
  script:
    - >
      if [ -n "$CI_COMMIT_TAG" ] && [ -n "$TYPO3_API_TOKEN" ] && [ -n "$TYPO3_EXTENSION_KEY" ]; then
        echo -e "Preparing upload of release ${CI_COMMIT_TAG} to TER\n"
        # Cleanup before we upload
        git reset --hard HEAD && git clean -fx
        # Upload
        TAG_MESSAGE=`git tag -n10 -l $CI_COMMIT_TAG | sed 's/^[0-9.]*[ ]*//g'`
        echo "Uploading release ${CI_COMMIT_TAG} to TER"
        /tmp/vendor/bin/tailor ter:publish --comment "$TAG_MESSAGE" "$CI_COMMIT_TAG" "$TYPO3_EXTENSION_KEY"
      fi;
```

## Author & License

Created by Benni Mack and Oliver Bartsch in 2020.

[rest-api]: https://extensions.typo3.org/faq/rest-api/
[ter]: https://extensions.typo3.org
[tailor-ext]: https://github.com/o-ba/tailor_ext
