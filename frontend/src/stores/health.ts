import { defineStore } from 'pinia'

import * as healthApi from '@/api/health'
import type { LabTestName, LabTrend, TimelineEvent } from '@/types'

interface HealthState {
  testNames: LabTestName[]
  namesLoading: boolean
  namesError: string | null
  selectedTest: string | null
  trend: LabTrend | null
  trendLoading: boolean
  trendError: string | null
  timeline: TimelineEvent[]
  timelineLoading: boolean
  timelineError: string | null
}

export const useHealthStore = defineStore('health', {
  state: (): HealthState => ({
    testNames: [],
    namesLoading: false,
    namesError: null,
    selectedTest: null,
    trend: null,
    trendLoading: false,
    trendError: null,
    timeline: [],
    timelineLoading: false,
    timelineError: null,
  }),

  actions: {
    async fetchTestNames() {
      this.namesLoading = true
      this.namesError = null
      try {
        this.testNames = await healthApi.getLabTestNames()
        if (!this.selectedTest && this.testNames.length > 0) {
          this.selectedTest = this.testNames[0].name
        }
      } catch {
        this.namesError = 'Unable to load lab tests.'
      } finally {
        this.namesLoading = false
      }
    },

    async fetchTrend(name: string) {
      this.selectedTest = name
      this.trendLoading = true
      this.trendError = null
      try {
        this.trend = await healthApi.getLabTrend(name)
      } catch {
        this.trendError = 'Unable to load trends.'
      } finally {
        this.trendLoading = false
      }
    },

    async fetchTimeline() {
      this.timelineLoading = true
      this.timelineError = null
      try {
        this.timeline = await healthApi.getTimeline()
      } catch {
        this.timelineError = 'Unable to load your timeline.'
      } finally {
        this.timelineLoading = false
      }
    },
  },
})