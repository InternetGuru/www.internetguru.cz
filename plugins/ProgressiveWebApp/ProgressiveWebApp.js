'serviceWorker' in navigator && navigator.serviceWorker.register('/serviceWorker.js')
  .then(function () {
    // console.log('Registered')
  })
  .catch(function (error) {
    console.log('Registration failed:', error)
  })
