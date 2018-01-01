# Duplicity

Duplicity is an experimental [WP-CLI](https://wp-cli.org/) plugin for detecting and deleting duplicate WordPress file attachments.

Its primary goal is reducing the physical number of files stored on a server, helping to speed up subsequent operations like scan, sync, and backup.

When duplicates are detected, all but the prototype file are deleted from the filesystem. The original attachment IDs, however, are retained to prevent any custom_field relations from breaking (the attachment sources are just remapped to the original).



##### Table of Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Use](#use)
4. [License](#license)


&nbsp;

## Requirements

 * PHP 5.6+;
 * A *nix OS;
 * WP-CLI;

Duplicity is __not__ compatible with WordPress Multi-Site installations.


&nbsp;

## Installation

You can manually download [duplicity.zip](https://raw.githubusercontent.com/Blobfolio/duplicity/master/release/duplicity.zip) and extract it somewhere on your server.

Debian-based servers can also install Duplicity using Blobfolio's APT repository:

```bash
# Import the signing key
wget -qO - https://apt.blobfolio.com/public.gpg.key | apt-key add -

# apt.blobfolio.com requires HTTPS connection support.
# This may or may not already be configured on your
# machine. If APT is unable to connect, install:
apt-get install apt-transport-https

# Debian Stretch
echo "deb [arch=amd64] https://apt.blobfolio.com/debian/ stretch main" > /etc/apt/sources.list.d/blobfolio.list

# Ubuntu Artful
echo "deb [arch=amd64] https://apt.blobfolio.com/debian/ artful main" > /etc/apt/sources.list.d/blobfolio.list

# Update APT sources
apt-get update

# Install it! Note: this will also install the "wp-cli"
# package, if not present.
apt-get install wp-cli-duplicity
```

Once you have the files on your server, they will need to be added to the WP-CLI [configuration](https://make.wordpress.org/cli/handbook/config/#config-files).

```
require:
  - /opt/duplicity/index.php
```

WP-CLI automatically recognizes the following generic configuration paths:
 
 * `/site/root/wp-cli.local.yml`
 * `/site/root/wp-cli.yml`
 * `~/.wp-cli/config.yml`

The `.deb` package comes with an example configuration that can be used if you don't need to specify any other options.

```bash
# Install as a symlink.
ln -s /usr/share/duplicity/wp-cli.local.yml /your/preferred/config/path

# Or copy it.
cp -a /usr/share/duplicity/wp-cli.local.yml /your/preferred/config/path
```

To verify that the plugin is working correctly, `cd` to a site root and type:

```bash
# This should return information about Duplicity's subcommands.
wp duplicity --help
```


&nbsp;

## Use

Duplicity includes the following commands for managing duplicate file attachments:

| Command     | Description                            |
| ----------- | -------------------------------------- |
| list        | Show duplicate and deduplicated files  |
| deduplicate | Run file deduplication                 |
| orphans     | Show and/or remove orphaned files      |
| postprocess | Manually run postprocess operations    |

Command reference is available in the usual fashion:

```bash
# e.g. type any of the following from a site's root.
wp duplicity list --help
wp duplicity deduplicate --help
wp duplicity orphans --help
wp duplicity postprocess --help
```

__Please__ make sure you have a backup of your files and database before proceeding!


&nbsp;

## License

Copyright © 2018 [Blobfolio, LLC](https://blobfolio.com) &lt;hello@blobfolio.com&gt;

This work is free. You can redistribute it and/or modify it under the terms of the Do What The Fuck You Want To Public License, Version 2.

    DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
    Version 2, December 2004
    
    Copyright (C) 2004 Sam Hocevar <sam@hocevar.net>
    
    Everyone is permitted to copy and distribute verbatim or modified
    copies of this license document, and changing it is allowed as long
    as the name is changed.
    
    DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
    TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION
    
    0. You just DO WHAT THE FUCK YOU WANT TO.

### Donations

<table>
  <tbody>
    <tr>
      <td width="200"><img src="https://blobfolio.com/wp-content/themes/b3/svg/btc-github.svg" width="200" height="200" alt="Bitcoin QR" /></td>
      <td width="450">If you have found this work useful and would like to contribute financially, Bitcoin tips are always welcome!<br /><br /><strong>1PQhurwP2mcM8rHynYMzzs4KSKpBbVz5is</strong></td>
    </tr>
  </tbody>
</table>
