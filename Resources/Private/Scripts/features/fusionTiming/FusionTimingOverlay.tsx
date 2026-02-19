import { useComputed, useSignal } from '@preact/signals'
import { useCallback } from 'preact/hooks'

import { useDebugContext } from '../../context/DebugContext'
import { css } from '../../styles/css'
import Overlay, { overlayState } from '../../presentationals/Overlay'
import Notice from '../../presentationals/Notice'
import Table from '../../presentationals/Table'
import { Icon, iconToggleOn, iconToggleOff, iconDownload } from '../../presentationals/Icon'

type SortKey = 'fusionPath' | 'count' | 'totalTime' | 'maxTime' | 'avgTime' | 'totalSqlQueries'
type SortDirection = 'asc' | 'desc'

const filterStyle = css`
    display: flex;
    gap: 0.5rem;
    align-items: center;

    input {
        background-color: var(--colors-ContrastNeutral);
        border: 1px solid var(--colors-ContrastDark);
        color: var(--colors-ContrastBrightest);
        font-size: 0.9em;
        padding: 0.4rem 0.6rem;
        flex: 1;
        box-sizing: border-box;

        &::placeholder {
            color: var(--colors-ContrastBright);
        }
    }
`

const toggleButtonStyle = css`
    background: none;
    border: 1px solid var(--colors-ContrastDark);
    color: var(--colors-ContrastBrightest);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.4rem 0.6rem;
    font-size: 0.85em;
    white-space: nowrap;

    &:hover {
        color: var(--colors-PrimaryBlue);
    }
`

const sortableHeaderStyle = css`
    cursor: pointer;
    user-select: none;
    white-space: nowrap;

    &:hover {
        color: var(--colors-PrimaryBlue);
    }
`

const slowStyle = css`
    color: var(--colors-Error);
`

const warnStyle = css`
    color: var(--colors-Warn);
`

const pathCellStyle = css`
    word-break: break-all;
    font-family: monospace;
    font-size: 0.85em;
`

const FusionTimingOverlay = () => {
    const visible = useComputed(() => overlayState.value === 'fusionTiming')
    const {
        debugInfos: { fusionPathTimings, fusionTraceEvents, renderTime },
    } = useDebugContext()

    const filter = useSignal('')
    const hideFast = useSignal(false)
    const sortKey = useSignal<SortKey>('fusionPath')
    const sortDirection = useSignal<SortDirection>('asc')

    const handleSort = useCallback((key: SortKey) => {
        if (sortKey.value === key) {
            sortDirection.value = sortDirection.value === 'desc' ? 'asc' : 'desc'
        } else {
            sortKey.value = key
            sortDirection.value = 'desc'
        }
    }, [])

    const handleExportTrace = useCallback(() => {
        const events = fusionTraceEvents || []
        if (!events.length) return

        const traceEvents = events.map((event) => ({
            name: event.fusionObjectType
                ? `${event.fusionObjectType} (${event.name.split('/').pop()})`
                : event.name,
            cat: event.fusionObjectType || 'fusion',
            ph: 'X',
            ts: event.startTime * 1000,
            dur: event.duration * 1000,
            pid: 1,
            tid: event.depth,
            args: {
                fusionPath: event.name,
                fusionObjectType: event.fusionObjectType,
                sqlQueries: event.sqlQueries,
            },
        }))

        const blob = new Blob([JSON.stringify({ traceEvents })], { type: 'application/json' })
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `fusion-trace-${Date.now()}.json`
        a.click()
        URL.revokeObjectURL(url)
    }, [])

    if (!visible.value) return null

    const timings = fusionPathTimings || []

    const filtered = timings
        .filter((t) => !hideFast.value || t.totalTime > 0.5)
        .filter((t) => !filter.value || t.fusionPath.toLowerCase().includes(filter.value.toLowerCase()))
        .sort((a, b) => {
            const aVal = a[sortKey.value]
            const bVal = b[sortKey.value]
            if (typeof aVal === 'string' && typeof bVal === 'string') {
                return sortDirection.value === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal)
            }
            return sortDirection.value === 'asc' ? (aVal as number) - (bVal as number) : (bVal as number) - (aVal as number)
        })

    const totalTime = renderTime ?? 0

    const sortIndicator = (key: SortKey) => (sortKey.value === key ? (sortDirection.value === 'desc' ? ' \u25BC' : ' \u25B2') : '')

    const timeClass = (ms: number) => (ms > 100 ? slowStyle : ms > 50 ? warnStyle : '')

    return (
        <Overlay title="Fusion path timing">
            <Notice>
                Showing <strong>{filtered.length}</strong> of <strong>{timings.length}</strong> unique paths, total render
                time <strong>{totalTime.toFixed(2)}ms</strong>
            </Notice>
            <div className={filterStyle}>
                <input
                    type="text"
                    placeholder="Filter by fusion path..."
                    value={filter.value}
                    onInput={(e) => (filter.value = (e.target as HTMLInputElement).value)}
                />
                <button
                    type="button"
                    className={toggleButtonStyle}
                    onClick={() => (hideFast.value = !hideFast.value)}
                    title="Hide entries with totalTime ≤ 0.5ms"
                >
                    <Icon icon={hideFast.value ? iconToggleOn : iconToggleOff} />
                    Hide ≤ 0.5ms
                </button>
                <button
                    type="button"
                    className={toggleButtonStyle}
                    onClick={handleExportTrace}
                    title="Export as Chrome Trace Format (load in chrome://tracing or Perfetto)"
                >
                    <Icon icon={iconDownload} />
                    Export Trace
                </button>
            </div>
            <Table>
                <thead>
                    <tr>
                        <th className={sortableHeaderStyle} onClick={() => handleSort('fusionPath')} style={{ width: '100%' }}>
                            Fusion Path{sortIndicator('fusionPath')}
                        </th>
                        <th className={sortableHeaderStyle} onClick={() => handleSort('count')} style={{ whiteSpace: 'nowrap' }}>
                            Calls{sortIndicator('count')}
                        </th>
                        <th className={sortableHeaderStyle} onClick={() => handleSort('totalTime')} style={{ whiteSpace: 'nowrap' }}>
                            Total (ms){sortIndicator('totalTime')}
                        </th>
                        <th className={sortableHeaderStyle} onClick={() => handleSort('maxTime')} style={{ whiteSpace: 'nowrap' }}>
                            Max (ms){sortIndicator('maxTime')}
                        </th>
                        <th className={sortableHeaderStyle} onClick={() => handleSort('avgTime')} style={{ whiteSpace: 'nowrap' }}>
                            Avg (ms){sortIndicator('avgTime')}
                        </th>
                        <th className={sortableHeaderStyle} onClick={() => handleSort('totalSqlQueries')} style={{ whiteSpace: 'nowrap' }}>
                            SQL Queries{sortIndicator('totalSqlQueries')}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {filtered.map((timing) => (
                        <tr key={timing.fusionPath}>
                            <td className={pathCellStyle}>{timing.fusionPath}</td>
                            <td>{timing.count}</td>
                            <td className={timeClass(timing.totalTime)}>{timing.totalTime.toFixed(2)}</td>
                            <td className={timeClass(timing.maxTime)}>{timing.maxTime.toFixed(2)}</td>
                            <td className={timeClass(timing.avgTime)}>{timing.avgTime.toFixed(2)}</td>
                            <td>{timing.totalSqlQueries}</td>
                        </tr>
                    ))}
                </tbody>
            </Table>
        </Overlay>
    )
}

export default FusionTimingOverlay
