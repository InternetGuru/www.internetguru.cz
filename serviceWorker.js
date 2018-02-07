importScripts('/lib/sw-toolbox.js');
toolbox.router.get('/:path([^.]+)*', toolbox.networkFirst);
// toolbox.router.default = toolbox.networkFirst;
