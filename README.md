# ec2-prune-snapshots

If you're doing `ec2-create-snapshot` frequently, then you'll need to clear those out at some point. `ec2-prune-snapshots` takes all the hassle out of doing this.

## Features

 * Auto-detects all volumes
 * Ensures the most recent snapshot from each volume is not deleted
 * Saves one snapshot per volume on the first day of each month in the last [540] days
 * Saves one snapshot per volume on each Sunday in the last [30] days
 * Saves one snapshot per volume on each day in the last [7] days
 * Saves all snapshots in the last [3] days
 * Handle multiple snapshots per day (by deleting all but the most recent snapshot per volume per day, excluding the last [3] days)
 * Configurable on the command line (see below)
 * Uses the official AWS PHP SDK

## Configuration:

From the help message:

    $ php ec2-prune-snapshots.php -h
    ec2-prune-snapshots v0.1 by http://www.BenjieGillam.com/

    This script defaults to no action - specify -d to perform operations.
    Be sure to set your credentials in ~/.aws/sdk/config.inc.php as specified by the AWS SDK. See: https://aws.amazon.com/articles/4261#configurecredentials

    Usage:
      -h		Help
      -v		Verbose (specify multiple times for greater verbosity)
      -q		Quiet
      -d		Actually perform operations (delete/do it)
      -a365:30:7:3	Set global options
      -v'vol-abcdefgh:365:30:7:3'	Set options for specific volume

    Options are specified as 4 ages, in days, for each operation
      1st: delete all older snapshots
      2nd: delete older unless 1st of month
      3rd: delete older unless Sunday or 1st of month
      4th: keep only one per day older than this

      Snapshots newer than the 4th parameter will be kept.

# Inspired by

My thanks to [Eric Dasque's ec2-manage-snapshots][ec2-manage-snapshots] which in turn was inspired by [Oren Solomianik’s ec2-delete-old-snapshots][ec2-delete-old-snapshots]. This new version is a ground up rewrite using the newest AWS PHP SDK and implementing slightly more fine grained handling of snapshots.

[ec2-manage-snapshots]: https://github.com/edasque/ec2-manage-snapshots
[ec2-delete-old-snapshots]: http://code.google.com/p/ec2-delete-old-snapshots/

# DISCLAIMER

THIS SOFTWARE IS PROVIDED ``AS IS'' AND WITHOUT ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, WITHOUT LIMITATION, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE.
