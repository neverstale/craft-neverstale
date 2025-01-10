type DateOptions = {
  locale?: string
  showTime?: boolean
  dateStyle?: 'full' | 'long' | 'medium' | 'short'
  timeStyle?: 'full' | 'long' | 'medium' | 'short'
}

export function formatDate(
  date: string,
  options: DateOptions = { showTime: false, dateStyle: 'short' },
): string {
  let formatOptions: Intl.DateTimeFormatOptions = {}

  if (options.showTime) {
    formatOptions = {
      timeStyle: options.timeStyle || 'short',
      timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    }
  }

  return Intl.DateTimeFormat(options.locale || 'en-US', {
    dateStyle: options.dateStyle || 'short',
    ...formatOptions,
  })
    .format(new Date(date))
}
