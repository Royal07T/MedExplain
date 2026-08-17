import { onBeforeUnmount, ref } from 'vue'

/**
 * Runs `task` immediately, then every `intervalMs` while the poller is active.
 * Returns a `stop` that cancels the timer and an `active` ref.
 */
export function usePolling(task: () => Promise<void> | void, intervalMs = 3000) {
  const active = ref(false)
  let timer: ReturnType<typeof setInterval> | null = null

  async function safeTask() {
    try {
      await task()
    } catch {
      // Individual poll failures are handled by the caller's state.
    }
  }

  async function start() {
    active.value = true
    await safeTask()
    if (active.value) {
      timer = setInterval(safeTask, intervalMs)
    }
  }

  function stop() {
    active.value = false
    if (timer !== null) {
      clearInterval(timer)
      timer = null
    }
  }

  onBeforeUnmount(stop)

  return { start, stop, active }
}