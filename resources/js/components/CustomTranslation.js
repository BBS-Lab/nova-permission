export default {
  methods: {
    trans(key, replace) {
      return window.config.translations['permission-builder::' + key]
        ? this.__('permission-builder::' + key, replace)
        : key
    }
  }
}
