# urchin

`urchin` is a simple PHP command line tool for generating a helper class for retrieving versioned assets output from [Vite](https://vitejs.dev/), removing the overhead of having to parse the manifest yourself.

---

## Installation

Download the `urchin.phar` from the latest release.

## Usage

```shell
php urchin.phar generate [--namespace NAMESPACE] [-d|--delete-manifest] [-p|--path-prefix [PATH-PREFIX]] [--] <helper-dir> <manifest-dir>
```

## Example

```console
php urchin.phar generate ./path/to/generated/class/dir ./path/to/manifest/file/dir --namespace=My\Namespace -d -p /assets
```

The above command will generate an `Assets.php` file located at `./path/to/generated/class/dir/Assets.php` with the namespace `My\Namespace` from assets located in the manifest.json file at `./path/to/manifest/file/dir/manifest.json`. The `-d` flag will delete the manifest.json after generating the class and the `-p` flag prefixes all versioned asset paths with `/assets/`, for example: `/assets/D7OVSBYbMnla6we6GLXVh.css`

![Generated helper class](./art/class.png)
