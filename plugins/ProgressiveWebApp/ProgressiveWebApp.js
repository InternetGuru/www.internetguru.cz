'serviceWorker' in navigator && navigator.serviceWorker.register('/sw.js')
  .then(function () {
    // console.log('Registered')
  })
  .catch(function (error) {
    console.log('Registration failed:', error)
  })
