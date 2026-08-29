/**
 * Composable for exporting table data to various formats
 */

export interface TableColumn {
  key?: string
  name?: string
  label: string
  exportable?: boolean
}

export function useTableExport() {
  /**
   * Columns may declare their field as `key` or `name` (Table.vue accepts
   * both); export follows the same convention.
   */
  function columnKey(col: TableColumn): string {
    return col.key ?? col.name ?? ''
  }
  /**
   * Export table data to CSV format
   */
  function exportToCSV(data: Record<string, unknown>[], columns: TableColumn[], filename = 'export.csv'): void {
    // Filter columns to only include exportable ones
    const exportableColumns = columns.filter(col => col.exportable !== false && columnKey(col))

    // Create CSV header
    const headers = exportableColumns.map(col => col.label).join(',')

    // Create CSV rows
    const rows = data.map(record => {
      return exportableColumns
        .map(col => {
          const value = getNestedValue(record, columnKey(col))
          // Escape quotes and wrap in quotes if contains comma or quotes
          const stringValue = String(value ?? '')
          if (stringValue.includes(',') || stringValue.includes('"') || stringValue.includes('\n')) {
            return `"${stringValue.replace(/"/g, '""')}"`
          }
          return stringValue
        })
        .join(',')
    })

    // Combine header and rows
    const csv = [headers, ...rows].join('\n')

    // Create blob and download
    downloadFile(csv, filename, 'text/csv;charset=utf-8;')
  }

  /**
   * Export table data to Excel format (actually TSV for better compatibility)
   */
  function exportToExcel(data: Record<string, unknown>[], columns: TableColumn[], filename = 'export.xlsx'): void {
    // Filter columns to only include exportable ones
    const exportableColumns = columns.filter(col => col.exportable !== false && columnKey(col))

    // Create TSV header
    const headers = exportableColumns.map(col => col.label).join('\t')

    // Create TSV rows
    const rows = data.map(record => {
      return exportableColumns
        .map(col => {
          const value = getNestedValue(record, columnKey(col))
          return String(value ?? '').replace(/\t/g, ' ')
        })
        .join('\t')
    })

    // Combine header and rows
    const tsv = [headers, ...rows].join('\n')

    // Create blob and download (using .xls extension for Excel compatibility)
    downloadFile(tsv, filename.replace('.xlsx', '.xls'), 'application/vnd.ms-excel;charset=utf-8;')
  }

  /**
   * Export table data to JSON format
   */
  function exportToJSON(data: Record<string, unknown>[], columns: TableColumn[], filename = 'export.json'): void {
    // Filter columns to only include exportable ones
    const exportableColumns = columns.filter(col => col.exportable !== false && columnKey(col))

    // Create array of objects with only exportable columns
    const exportData = data.map(record => {
      const obj: Record<string, unknown> = {}
      exportableColumns.forEach(col => {
        obj[columnKey(col)] = getNestedValue(record, columnKey(col))
      })
      return obj
    })

    // Convert to JSON string with formatting
    const json = JSON.stringify(exportData, null, 2)

    // Create blob and download
    downloadFile(json, filename, 'application/json;charset=utf-8;')
  }

  /**
   * Export current view (filtered/sorted data) to specified format
   */
  function exportView(data: Record<string, unknown>[], columns: TableColumn[], format = 'csv', filename = 'export'): void {
    const fullFilename = filename.includes('.')
      ? filename
      : `${filename}.${format}`

    switch (format.toLowerCase()) {
      case 'csv':
        exportToCSV(data, columns, fullFilename)
        break
      case 'excel':
      case 'xls':
      case 'xlsx':
        exportToExcel(data, columns, fullFilename)
        break
      case 'json':
        exportToJSON(data, columns, fullFilename)
        break
      default:
        console.error(`Unsupported export format: ${format}`)
    }
  }

  /**
   * Helper function to get nested object values using dot notation
   */
  function getNestedValue(obj: Record<string, unknown>, path: string): unknown {
    return path.split('.').reduce((current: unknown, key) => {
      if (current && typeof current === 'object' && key in current) {
        return (current as Record<string, unknown>)[key]
      }
      return undefined
    }, obj)
  }

  /**
   * Helper function to trigger file download
   */
  function downloadFile(content: string, filename: string, mimeType: string): void {
    const blob = new Blob([content], { type: mimeType })
    const link = document.createElement('a')

    if (link.download !== undefined) {
      // Feature detection for download attribute
      const url = URL.createObjectURL(blob)
      link.setAttribute('href', url)
      link.setAttribute('download', filename)
      link.style.visibility = 'hidden'
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(url)
    }
  }

  /**
   * Format table data for printing
   */
  function printTable(data: Record<string, unknown>[], columns: TableColumn[], title = 'Table Data'): void {
    // Filter columns to only include exportable ones
    const exportableColumns = columns.filter(col => col.exportable !== false && columnKey(col))

    // Create HTML table
    const html = `
      <!DOCTYPE html>
      <html>
      <head>
        <title>${title}</title>
        <style>
          body {
            font-family: Arial, sans-serif;
            padding: 20px;
          }
          h1 {
            font-size: 24px;
            margin-bottom: 20px;
          }
          table {
            width: 100%;
            border-collapse: collapse;
          }
          th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
          }
          th {
            background-color: #f3f4f6;
            font-weight: 600;
          }
          tr:nth-child(even) {
            background-color: #f9fafb;
          }
          @media print {
            body {
              padding: 0;
            }
          }
        </style>
      </head>
      <body>
        <h1>${title}</h1>
        <table>
          <thead>
            <tr>
              ${exportableColumns.map(col => `<th>${col.label}</th>`).join('')}
            </tr>
          </thead>
          <tbody>
            ${data.map(record => `
              <tr>
                ${exportableColumns.map(col => `<td>${getNestedValue(record, columnKey(col)) ?? ''}</td>`).join('')}
              </tr>
            `).join('')}
          </tbody>
        </table>
      </body>
      </html>
    `

    // Open print dialog
    const printWindow = window.open('', '_blank')
    if (printWindow) {
      printWindow.document.write(html)
      printWindow.document.close()
      printWindow.focus()
      printWindow.print()
      printWindow.close()
    }
  }

  return {
    exportToCSV,
    exportToExcel,
    exportToJSON,
    exportView,
    printTable,
  }
}
