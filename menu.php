<?php

function template($string, $templates) {
  $template_pattern = '/\${(\w+)}/';

  $matches = [];
  $match_count = preg_match_all($template_pattern, $string, $matches);

  if ($match_count) {
    for ($i = 0; $i < $match_count; $i++) {
      $string = str_replace($matches[0][$i], $templates[$matches[1][$i]], $string);
    }
  }
  return $string;
}

function addFileTarget($root, $http_root, $path, $host) {
  $pathinfo = pathinfo($path);
  $extension = $pathinfo['extension'];
  $target_name = $pathinfo['filename'];
  $relative_path = str_replace($root, '', $path);

  // ISOs get booted like SAN targets
  if ($extension == 'iso') {
    return [$target_name => "sanboot $host/$http_root/$relative_path"];

  // EFI scripts get chainloaded
  } else if ($extension == 'pxe') {
    return [$target_name => "chain --autofree $host/$http_root/$relative_path"];

  // TXT files contain custom entries
  } else if ($extension == 'txt') {
    return [$target_name => trim(file_get_contents($path))];
  }
}

function walkDirectory($root, $http_root, $host) {
  $targets = [];
  $directory_name = basename($root);

  // Attempt to load a config file
  if (file_exists("$root/config.ini")) {
    $kernel_targets = parse_ini_file("$root/config.ini", true);
  }

  if ( isset( $kernel_targets ) ) {
    foreach( $kernel_targets as $target_name => $target ) {
      $kernel      = $target['kernel'];
      $initrd      = $target['initrd'];
      $kernel_args = "initrd=$initrd " . $target['bootargs'];

      $kernel_args = template($kernel_args, ['hostpath'=>"$host/$root/"]);

      // TODO For debian
      //$kernel_args = "nomodeset initrd=$initrd fetch=$host/squashfs";

      $boot_cmd = "kernel $host/$http_root/$kernel $kernel_args && " .
                  "initrd $host/$http_root/$initrd && " .
                  "boot";

      $targets[$target_name] = $boot_cmd;
    }

    return [$directory_name => $targets];
  }

  // $root isn't a netboot directory, iterate through every file
  $files = new RecursiveDirectoryIterator($root,
      RecursiveDirectoryIterator::SKIP_DOTS);

  foreach($files as $file) {
    $path = $file->getPathname();
    if ($file->isDir()) {
      $directory_targets = walkDirectory($path, $http_root, $host);
      $targets = array_merge_recursive ($targets, $directory_targets);
    } else if ($file->isFile()) {
      $file_target = addFileTarget($root, $http_root, $path, $host);
      if ($file_target)
        $targets = array_merge_recursive ($targets, $file_target);
    }
  }

  return [$directory_name => $targets];
}

function generateMenu($targets, $labels) {
  $menu = [];
  foreach($targets as $label => $target) {
    if ( is_array($target) ) {
      if ($labels) array_push($menu, "item --gap $label");
      $menu = array_merge($menu, generateMenu($target, $labels));
    } else {
      $key = md5($target);
      if ($labels) array_push($menu, "item $key - Boot $label");
      else array_push($menu, "iseq \${boot} $key && $target ||");
    }
  }
  return $menu;
}

function getMenu($root, $http_root) {
  if (isset($_SERVER['HTTP_HOST'])) $host = 'http://'.$_SERVER['HTTP_HOST'].'/';
  else $host = 'http://example.com/';

  $menu = ["#!ipxe"];

  array_push($menu, "menu Select Boot Option");

  $targets = walkDirectory($root, $http_root, $host)[basename($root)];

  $menu = array_merge($menu, generateMenu($targets, true));

  // Display the menu selector
  array_push($menu, "choose boot");

  $menu = array_merge($menu, generateMenu($targets, false));

  return $menu;
}

header("Content-Type: text/plain");

$config = parse_ini_file('config/config.ini');
$menu = getMenu($config['boot_root'], $config['http_root']);

foreach ($menu as $line)
  echo("$line\n");

