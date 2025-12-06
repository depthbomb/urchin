# urchin

`urchin` is a simple PHP command line tool for generating a helper class for retrieving versioned assets output from [Vite](https://vitejs.dev/), removing the overhead of having to parse the manifest yourself.

---

## Installation

Download the `urchin.phar` from the latest release.

## Usage

```shell
Usage:
  command [options] [arguments]

Options:
  -h, --help            Display help for the given command. When no command is given display help for the list command
      --silent          Do not output any message
  -q, --quiet           Only errors are displayed. All other output is suppressed
  -V, --version         Display this application version
      --ansi|--no-ansi  Force (or disable --no-ansi) ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  completion  Dump the shell completion script
  generate    Generates an asset helper class based off of Vite's output manifest.json
  help        Display help for a command
  list        List commands
```

## Example

```console
php urchin.phar generate ./path/to/generated/class/dir ./path/to/manifest/file/dir --namespace=My\Namespace -d
```

The above command will generate an `Assets.php` file located at `./path/to/generated/class/dir/Assets.php` with the namespace `My\Namespace` from assets located in the manifest.json file at `./path/to/manifest/file/dir/manifest.json`. The `-d` flag will delete the manifest.json after generating the class.

![Generated helper class](./art/class.png)
