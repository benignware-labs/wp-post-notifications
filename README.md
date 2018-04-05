# wp-post-notifications

Let users subscribe to post updates

## Usage

Place shortcode inside post content

```
[post_notifications]
```

### Customize

Customize by programmatically adding attributes to shortcode

```php
// Customize post_notifications shortcode
function custom_shortcode_atts_post_notifications($out, $pairs, $atts, $shortcode) {
  $result = array_merge($out, array(
    'template' => 'file://path-to-template-file.php'
  ), $atts);
  return $result;
}
add_filter( 'shortcode_atts_post_notifications', 'custom_shortcode_atts_post_notifications', 10, 4);

## Development

Download [Docker CE](https://www.docker.com/get-docker) for your OS.

### Environment

Point terminal to your project root and start up the container.

```cli
docker-compose up -d
```

Open your browser at [http://localhost:3010](http://localhost:3010).

Go through Wordpress installation and activate this plugin.

### Useful docker commands

#### Startup services

```cli
docker-compose up -d
```
You may omit the `-d`-flag for verbose output.

#### Shutdown services

In order to shutdown services, issue the following command

```cli
docker-compose down
```

#### List containers

```cli
docker-compose ps
```

#### Remove containers

```cli
docker-compose rm
```

#### Open bash

Open bash at wordpress directory

```cli
docker-compose exec wordpress bash
```

#### Update composer dependencies

If it's complaining about the composer.lock file, you probably need to update the dependencies.

```cli
docker-compose run composer update
```

###### List all globally running docker containers

```cli
docker ps
```

###### Globally stop all running docker containers

If you're working with multiple docker projects running on the same ports, you may want to stop all services globally.

```cli
docker stop $(docker ps -a -q)
```

###### Globally remove all containers

```cli
docker rm $(docker ps -a -q)
```

##### Remove all docker related stuff

```cli
docker system prune
```
