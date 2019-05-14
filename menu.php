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

function addFileTarget($path, $host) {
  $pathinfo = pathinfo($path);
  $extension = $pathinfo['extension'];
  $target_name = $pathinfo['filename'];

  // ISOs get booted like SAN targets
  if ($extension == 'iso') {
    return [$target_name => "sanboot $host/$path"];

  // EFI scripts get chainloaded
  } else if ($extension == 'pxe') {
    return [$target_name => "chain --autofree $host/path"];

  // TXT files contain custom entries
  } else if ($extension == 'txt') {
    return [$target_name => trim(file_get_contents($path))];
  }
}

function walkDirectory($root, $host) {
  $targets = [];
  $directory_name = basename($root);

  // Attempt to load a config file
  if (file_exists("$root/config.ini")) {
    $kernel_targets = parse_ini_file("$root/config.ini", true);
  }

  if ( isset( $kernel_targets ) ) {
    foreach( $kernel_targets as $target_name => $target ) {
      if (array_key_exists('kernel', $target)) {
        $kernel      = $target['kernel'];
        $initrd      = $target['initrd'];
        $kernel_args = "initrd=$initrd " . $target['bootargs'];

        $kernel_args = template($kernel_args, ['hostpath'=>"$host/$root/"]);

        // TODO For debian
        //$kernel_args = "nomodeset initrd=$initrd fetch=$host/squashfs";

        $boot_cmd = "kernel $host/$root/$kernel $kernel_args && " .
                    "initrd $host/$root/$initrd && " .
                    "boot";

        $targets[$target_name] = $boot_cmd;
      } else if (array_key_exists('wimboot',$target)) {
        $wimboot      = $target['wimboot'];
        $image        = $target['image'];
        $bcd          = $target['bcd'];
        $boot         = $target['boot'];

        $boot_cmd = "kernel $host/$root/$wimboot && " .
                    "initrd $host/$root/$bcd     BCD && " .
                    "initrd $host/$root/$boot    boot.sdi && " .
                    "initrd $host/$root/$image boot.wim && " .
                    "boot";

        $targets[$target_name] = $boot_cmd;
      }
    }

    return [$directory_name => $targets];
  }

  // $root isn't a netboot directory, iterate through every file
  $files = new RecursiveDirectoryIterator($root,
      RecursiveDirectoryIterator::SKIP_DOTS);

  foreach($files as $file) {
    $path = $file->getPathname();
    if ($file->isDir()) {
      $directory_targets = walkDirectory($path, $host);
      $targets = array_merge_recursive ($targets, $directory_targets);
    } else if ($file->isFile()) {
      $file_target = addFileTarget($path, $host);
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

function getMenu($root) {
  if (isset($_SERVER['HTTP_HOST'])) $host = 'http://'.$_SERVER['HTTP_HOST'].'/';
  else $host = 'http://example.com/';

  $menu = ["#!ipxe"];

  array_push($menu, "menu Select Boot Option");

  $targets = walkDirectory($root, $host)[basename($root)];

  $menu = array_merge($menu, generateMenu($targets, true));

  // Display the menu selector
  array_push($menu, "choose boot");

  $menu = array_merge($menu, generateMenu($targets, false));

  return $menu;
}

header("Content-Type: text/plain");

$menu = getMenu('./static');
foreach ($menu as $line)
  echo("$line\n");

