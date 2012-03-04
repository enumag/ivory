foreach ($include as $file) {
	@include $file . '.iss';
}