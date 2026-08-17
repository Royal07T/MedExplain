import { config } from '@vue/test-utils'

// jsdom does not implement URL.createObjectURL.
Object.defineProperty(URL, 'createObjectURL', {
  writable: true,
  value: () => 'blob:fake',
})

config.global.stubs = {
  RouterLink: true,
  RouterView: true,
}