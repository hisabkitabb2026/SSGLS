/**
 * useOperationsHelpers
 *
 * Shared utility functions used across the Fleet Management and Warehouse
 * Operations modules. Extracted from WarehouseItemIndexView, LoadTripIndexView,
 * ConsolidationBoardView, and ConsolidationDetailView to eliminate duplication.
 *
 * Functions:
 * - formatWeight: Formats kg values with Indian locale
 * - formatDate: Formats ISO dates to dd MMM yy
 * - getTripStatusClass: Tailwind classes for trip status badges
 * - getConsolidationStatusClass: Tailwind classes for consolidation status badges
 * - getFillBarClass: Tailwind classes for fill progress bars
 * - getDaysClass: Tailwind classes for aging/days-in-warehouse indicators
 * - getAgingClass: Tailwind classes for aging bucket cards
 * - getPriorityClass: Tailwind classes for priority badges
 */

export function useOperationsHelpers() {
  const formatWeight = (weight: any): string => {
    const w = parseFloat(weight) || 0
    return w.toLocaleString('en-IN', { maximumFractionDigits: 2 })
  }

  const formatDate = (date: string): string => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('en-IN', {
      day: '2-digit',
      month: 'short',
      year: '2-digit',
    })
  }

  const getTripStatusClass = (status: string): string => {
    const classes: Record<string, string> = {
      planned: 'bg-status-blue/10 text-status-blue',
      dispatched: 'bg-status-purple/10 text-status-purple',
      delivered: 'bg-status-green/10 text-status-green',
      cancelled: 'bg-status-red/10 text-status-red',
    }
    return classes[status] || ''
  }

  const getConsolidationStatusClass = (status: string): string => {
    const classes: Record<string, string> = {
      open: 'bg-status-blue/10 text-status-blue',
      ready: 'bg-status-green/10 text-status-green',
      dispatched: 'bg-status-purple/10 text-status-purple',
      completed: 'bg-surface-tertiary text-muted',
      cancelled: 'bg-status-red/10 text-status-red',
    }
    return classes[status] || ''
  }

  const getFillBarClass = (percentage: number): string => {
    const p = percentage || 0
    if (p >= 80) return 'bg-status-green'
    if (p >= 50) return 'bg-status-yellow'
    if (p > 0) return 'bg-status-blue'
    return 'bg-surface-tertiary'
  }

  const getDaysClass = (days: number): string => {
    if (days <= 3) return 'text-status-green'
    if (days <= 7) return 'text-status-yellow'
    if (days <= 15) return 'text-status-blue'
    return 'text-status-red'
  }

  const getAgingClass = (bucket: string): string => {
    const classes: Record<string, string> = {
      '0-3': 'border-l-4 border-status-green',
      '4-7': 'border-l-4 border-status-yellow',
      '8-15': 'border-l-4 border-status-blue',
      '15+': 'border-l-4 border-status-red',
    }
    return classes[bucket] || ''
  }

  const getPriorityClass = (priority: string): string => {
    const classes: Record<string, string> = {
      urgent: 'bg-status-yellow/10 text-status-yellow',
      critical: 'bg-status-red/10 text-status-red',
    }
    return classes[priority] || ''
  }

  return {
    formatWeight,
    formatDate,
    getTripStatusClass,
    getConsolidationStatusClass,
    getFillBarClass,
    getDaysClass,
    getAgingClass,
    getPriorityClass,
  }
}
