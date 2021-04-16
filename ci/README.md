# ECS Drush Update Script for CI/CD

## Table of Contents

- [Table of Contents](#table-of-contents)
- [About](#about)
- [Running](#running)
  - [Required Environment Variables](#required-environment-variables)
- [Drush Steps](#drush-steps)
- [Files](#files)
  - [`drush.js`](#drushjs)
  - [`ecs.js`](#ecsjs)
  - [`ssm.js`](#ssmjs)
  - [`ui.js`](#uijs)
  - [`util.js`](#utiljs)
  - [`vars.js`](#varsjs)
- [External Links](#external-links)

## About

## Running

Assuming the required environment variables are present (see below), these steps are sufficient to run the Drush updates:

```sh
$ cd ci
$ npm ci --production
$ node drush.js
```

### Required Environment Variables

- `$WEBCMS_ENVIRONMENT`
- `$WEBCMS_SITE`
- `$WEBCMS_LANG`
- `$WEBCMS_IMAGE_TAG`
- One of `$AWS_REGION` or `$AWS_DEFAULT_REGION` is strongly recommended

## Drush Steps

## Files

This is a fairly brief overview of the modules in this directory. Each file has more detailed documentation of both their exports and internals. Each top-level item, exported or not, is annotated with [JSDoc](https://jsdoc.app/) comments. We use the `// @ts-check` annotation to perform basic type-checking (see [JS Projects Utilizing TypeScript](https://www.typescriptlang.org/docs/handbook/intro-to-js-ts.html) in the TypeScript documentation).

### `drush.js`

This is the script for the Drush update job. It contains the following (unexported) top-level items:

- `const script: string` is the Drush update shell script.
- `function main(): Promise<void>` is the main async function. We wrap the asynchronous logic due to the (currently limited) support for top-level await in Node.js

### `ecs.js`

This module wraps the ECS API to handle the functionality this script needs. Its exports wrap ECS API calls, handling the boilerplate and breaking large response objects down, returning only the pieces needed by callers.

- `function stopRunningTasks(): Promise<number>` stops currently-running Drupal tasks. This is needed to avoid a cache corruption issue; see the function's documentation for a more thorough discussion. The promise it returns resolves to the number of tasks that were stopped so that this count can be displayed to the user.
- `function startDrushTask(script: string): Promise<string>` spawns the ECS Drush task, handling boilerplate details about the Fargate task networking and task overrides. The promise it returns resolves to the ARN of the task. Note that due to the asynchronous nature of ECS, this promise is resolved as soon as ECS acknowledges the task.
- `type Status` is an object containing the stop and exit information for a Drush task. This mirrors ECS' terminology: the stop information is task-wide (for example, a task can stop because an essential container exited or because the host EC2 instance terminated), and the exit information is container-specific (i.e., the UNIX process exit code/signal).
- `function getDrushStatus(task: string): Promise<string | Status>` queries ECS for the status of the given task ARN. If it returns a string, that means the task is still running and the string corresponds to an [ECS task lifecycle](https://docs.aws.amazon.com/AmazonECS/latest/developerguide/task-lifecycle.html) state. If it returns a `Status` object, then the Drush task completed and the object contains the information why it exited.

### `ssm.js`

This module wraps the AWS Systems Manager API with conveniences specific to this script's Parameter Store needs. Specifically, it knows about the naming conventions we use (i.e., that all parameter paths begin with `/webcms/${environment}/`) and uses an in-memory cache to avoid repeatedly loading the same parameter multiple times in the same script.

The primary reason this module exists is to make parameter reads more convenient. Using the SDK directly requires this sequence of steps:

```js
const command = new GetParameterCommand({
  Name: `/webcms/${vars.environment}/ecs/cluster-name`,
});
const response = await client.send(command);
const clusterName = response.Parameter.Value;
```

But the wrapper function is a single line:

```js
const clusterName = await ssm.getParameter("ecs/cluster-name");
```

- `function getParameter(suffix: string): Promise<string>` returns the value of the named parameter. This wraps the `GetParameter` API call with the memoization logic mentioned above.

### `ui.js`

This module contains presentation logic for CI/CD consoles. It encapsulates the differences in support for features the script uses, such as sectioning and hyperlinks. The reason for this is that most CI/CD consoles use ANSI escape sequences to handle this functionality, so we avoid outputting unsupported codes to prevent the web output being cluttered with partially-read "]123" sequences.

- `function link(url: string, content?: string): string` creates a hyperlink. If the CI/CD console supports it, the link is interactive (a la `<a href="${url}">${content}</a>`), but if not, it returns a Markdown-style link: `[${content}]: ${url}`. If `content` is not provided, it just returns the url.
- `function log(message?: string): void` prints a message to standard output. This acts roughly like `console.log(message)` except that it uses `process.stdout.write()` directly.
- `function logHeading(emoji: string, heading: string): void` prints an ASCII sectioning header. The `emoji` parameter is only used on Buildkite.
- `function notify(): void` prints, where supported, a notification to open a collapsed section. This is only useful on Buildkite (see [Collapsing Output](https://buildkite.com/docs/pipelines/managing-log-output#collapsing-output) in their documentation), and is a no-op in other CI/CD environments.

### `util.js`

This script contains miscellaneous utility information and logic not directly tied to display logic or AWS APIs.

- `function delay(): Promise<void>` returns a promise that waits for 5 seconds before resolving.
- `type ExitInfo` holds presentational information for the stop and exit reasons for the Drush task.
- `function inspectDrushStatus(status: ecs.Status): ExitInfo` transforms the ECS exit status into a presentational `ExitInfo` object.
- `function getLogsUrl(task: string): Promise<string>` returns a direct link to the CloudWatch logs for the given Drush task ARN.
- `function getTaskUrl(task: string): Promise<string>` returns a direct link to the ECS task page for the given Drush task ARN.

### `vars.js`

This module is responsible for transforming the `$WEBCMS_*` environment variables into JavaScript constants. If a required variable is not present, the script throws an error in initialization.

- `const environment: string` is the environment the script is targeting. Corresponds to `$WEBCMS_ENVIRONMENT` in CI/CD files.
- `const site: string` is the site the script is targeting. Corresponds to `$WEBCMS_SITE` in CI/CD files.
- `const lang: string` is the language the script is targeting. Corresponds to `$WEBCMS_LANG` in CI/CD files.
- `const imageTag: string` is the image tag for this build, used here to correlate spawned tasks to this pipeline execution. Corresponds to `$WEBCMS_IMAGE_TAG` in CI/CD files.
- `const region: string` is the AWS region in which this script is executing. This is read from `$AWS_REGION` in the environment, but also tries `$AWS_DEFAULT_REGION` before defaulting to `"us-east-1"`.

## External Links

- [AWS SDK v3 Developer Guide](https://docs.aws.amazon.com/sdk-for-javascript/v3/developer-guide/welcome.html)
- [AWS SDK v3 API Reference](https://docs.aws.amazon.com/AWSJavaScriptSDK/v3/latest/)
