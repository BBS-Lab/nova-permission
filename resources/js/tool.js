Nova.booting((Vue, router, store) => {
  router.addRoutes([
    {
      name: 'nova-permission',
      path: '/nova-permission',
      component: require('./components/PermissionBuilder')
    },
  ])
})
