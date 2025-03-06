# Contributing

Pull requests are welcome!

## Branching

PRs should be made against the `main` branch. The `main` branch is the default branch for the repository.

We prefer squash commits (i.e. avoid merge PRs) from feature branches into `main` when merging and to include the PR # in the commit message.

The `release` branch builds the downloadable asset (which excludes some of the files used for CI) that is used for GitHub releases.

## Release Process

1. When `main` is at a point where a release should be made, make sure to update the version in all the relevant places:
	- In `README.md`, update `Stable tag:`
	- In `plugin.php`, update `Version:`
	- In `plugin.php`, update the `TLS_CHECKER_VERSION` constant
	- Create a PR to merge these changes into `main` (or YOLO and push directly to `main`, not recommended).
1. Manually merge `main` into `release` and push the `release` branch.
    ```bash
	git checkout main && git pull
	git checkout release && git pull
	git merge main --ff-only
	git push
	```
1. After pushing to the `release` branch, a tag will be created automatically, along with a draft PR for the release.
1. Confirm that the necessary assets are present in the newly created tag, and test on a WordPress install if desired.
1. Review the release notes making any necessary changes and publish the release.
1. Publish the release. Packagist.org will update automatically.