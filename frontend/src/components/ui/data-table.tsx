import {
    flexRender,
    getCoreRowModel,
    useReactTable,
    getPaginationRowModel,
    getSortedRowModel,
    getFilteredRowModel,
} from "@tanstack/react-table"
import type {
    ColumnDef,
    SortingState,
    ColumnFiltersState,
} from "@tanstack/react-table"
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { useState } from "react"
import { Search, FileText, Download } from "lucide-react"
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
    PaginationInfo,
    usePagination
} from "@/components/ui/pagination"

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[]
    data: TData[]
    loading?: boolean
    pagination?: {
        currentPage: number
        totalPages: number
        totalItems: number
        itemsPerPage: number
        from: number
        to: number
        onPageChange: (page: number) => void
    }
    showPaginationInfo?: boolean
}

export function DataTable<TData, TValue>({
    columns,
    data,
    loading,
    pagination,
    showPaginationInfo = true,
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = useState<SortingState>([])
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([])

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        onSortingChange: setSorting,
        getSortedRowModel: getSortedRowModel(),
        onColumnFiltersChange: setColumnFilters,
        getFilteredRowModel: getFilteredRowModel(),
        state: {
            sorting,
            columnFilters,
        },
    })

    // Generate pagination items for the new component
    const paginationItems = pagination ? 
        usePagination({
            currentPage: pagination.currentPage,
            totalPages: pagination.totalPages,
            siblingCount: 1
        }) : 
        usePagination({
            currentPage: table.getState().pagination.pageIndex + 1,
            totalPages: table.getPageCount() || 1,
            siblingCount: 1
        })

    return (
        <div className="space-y-4">
            {/* Table Controls - Professional Standard */}
            <div className="flex items-center justify-between gap-4">
                <div className="relative max-w-sm flex-1">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <Input
                        placeholder="Filter results..."
                        value={(table.getColumn("name")?.getFilterValue() as string) ?? ""}
                        onChange={(event) =>
                            table.getColumn("name")?.setFilterValue(event.target.value)
                        }
                        className="pl-10 h-10 border-slate-200 bg-white focus:ring-blue-100 transition-all"
                    />
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="outline" size="sm" className="h-10 border-slate-200 text-slate-600 font-semibold gap-2">
                        <Download className="h-4 w-4" />
                        Export
                    </Button>
                    <Button variant="outline" size="sm" className="h-10 border-slate-200 text-slate-600 font-semibold gap-2">
                        <FileText className="h-4 w-4" />
                        Print
                    </Button>
                </div>
            </div>

            {/* Table Surface */}
            <div className="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
                <Table className="table-enterprise">
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id} className="hover:bg-transparent border-b border-slate-200">
                                {headerGroup.headers.map((header) => {
                                    return (
                                        <TableHead key={header.id} className="h-12 text-slate-500 font-bold px-4">
                                            {header.isPlaceholder
                                                ? null
                                                : flexRender(
                                                    header.column.columnDef.header,
                                                    header.getContext()
                                                )}
                                        </TableHead>
                                    )
                                })}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {loading ? (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="h-32 text-center"
                                >
                                    <div className="flex flex-col items-center gap-2">
                                        <span className="spinner h-6 w-6 text-blue-600" />
                                        <span className="text-sm font-medium text-slate-500">Retrieving records...</span>
                                    </div>
                                </TableCell>
                            </TableRow>
                        ) : table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow
                                    key={row.id}
                                    data-state={row.getIsSelected() && "selected"}
                                    className="border-b border-slate-100 last:border-0"
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id} className="px-4 py-3 text-slate-600 font-medium">
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext()
                                            )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="h-32 text-center text-slate-400 font-medium"
                                >
                                    No records found matching your criteria.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {/* Pagination Controls - Standardized */}
            {(pagination || table.getPageCount() > 1) && (
                <div className="flex flex-col sm:flex-row items-center justify-between gap-4 bg-slate-50/50 p-4 rounded-lg border border-slate-100">
                    {showPaginationInfo && (
                        pagination ? (
                            <PaginationInfo
                                currentPage={pagination.currentPage}
                                totalPages={pagination.totalPages}
                                totalItems={pagination.totalItems}
                                itemsPerPage={pagination.itemsPerPage}
                                from={pagination.from}
                                to={pagination.to}
                            />
                        ) : (
                            <div className="text-sm text-slate-500 font-medium">
                                Page {table.getState().pagination.pageIndex + 1} of {table.getPageCount() || 1}
                            </div>
                        )
                    )}
                    
                    <Pagination>
                        <PaginationContent>
                            {pagination ? (
                                // Server-side pagination
                                <>
                                    <PaginationItem>
                                        <PaginationPrevious 
                                            onClick={() => pagination.onPageChange(pagination.currentPage - 1)}
                                            disabled={pagination.currentPage === 1}
                                        />
                                    </PaginationItem>
                                    
                                    {paginationItems.map((item, index) => (
                                        item === "..." ? (
                                            <PaginationItem key={`ellipsis-${index}`}>
                                                <PaginationEllipsis />
                                            </PaginationItem>
                                        ) : (
                                            <PaginationItem key={item}>
                                                <PaginationLink
                                                    onClick={() => pagination.onPageChange(Number(item))}
                                                    isActive={Number(item) === pagination.currentPage}
                                                >
                                                    {item}
                                                </PaginationLink>
                                            </PaginationItem>
                                        )
                                    ))}
                                    
                                    <PaginationItem>
                                        <PaginationNext 
                                            onClick={() => pagination.onPageChange(pagination.currentPage + 1)}
                                            disabled={pagination.currentPage === pagination.totalPages}
                                        />
                                    </PaginationItem>
                                </>
                            ) : (
                                // Client-side pagination
                                <>
                                    <PaginationItem>
                                        <PaginationPrevious 
                                            onClick={() => table.previousPage()}
                                            disabled={!table.getCanPreviousPage()}
                                        />
                                    </PaginationItem>
                                    
                                    {paginationItems.map((item, index) => (
                                        item === "..." ? (
                                            <PaginationItem key={`ellipsis-${index}`}>
                                                <PaginationEllipsis />
                                            </PaginationItem>
                                        ) : (
                                            <PaginationItem key={item}>
                                                <PaginationLink
                                                    onClick={() => table.setPageIndex(Number(item) - 1)}
                                                    isActive={Number(item) === table.getState().pagination.pageIndex + 1}
                                                >
                                                    {item}
                                                </PaginationLink>
                                            </PaginationItem>
                                        )
                                    ))}
                                    
                                    <PaginationItem>
                                        <PaginationNext 
                                            onClick={() => table.nextPage()}
                                            disabled={!table.getCanNextPage()}
                                        />
                                    </PaginationItem>
                                </>
                            )}
                        </PaginationContent>
                    </Pagination>
                </div>
            )}
        </div>
    )
}