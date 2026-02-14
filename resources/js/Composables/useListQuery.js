import { computed } from "vue";
import { router } from "@inertiajs/vue3";

export function useListQuery(options) {
    const {
        path,
        filters,
        defaultSort = "id",
        defaultDirection = "desc",
        buildParams,
    } = options;

    const currentSearch = () => filters?.value?.search ?? filters?.search ?? "";
    const currentSort = () => filters?.value?.sort ?? filters?.sort ?? defaultSort;
    const currentDirection = () => filters?.value?.direction ?? filters?.direction ?? defaultDirection;
    const currentPath = () => {
        if (typeof path === "function") return path();
        if (typeof path === "string") return path;
        return path?.value ?? "";
    };
    const buildQuery = (searchValue, sortValue, directionValue) => {
        if (typeof buildParams === "function") {
            return buildParams({
                search: searchValue,
                sort: sortValue,
                direction: directionValue,
                filters: filters?.value ?? filters ?? {},
            });
        }

        return {
            search: searchValue,
            sort: sortValue,
            direction: directionValue,
        };
    };

    const search = computed({
        get: () => currentSearch(),
        set: (value) => {
            router.get(
                currentPath(),
                buildQuery(value, currentSort(), currentDirection()),
                { preserveState: true, replace: true }
            );
        },
    });

    const sortBy = (column) => {
        const isCurrent = currentSort() === column;
        const nextDirection = isCurrent && currentDirection() === "asc" ? "desc" : "asc";

        router.get(
            currentPath(),
            buildQuery(currentSearch(), column, nextDirection),
            { preserveState: true, replace: true }
        );
    };

    const sortIndicator = (column) => {
        if (currentSort() !== column) return "";
        return currentDirection() === "asc" ? "^" : "v";
    };

    return {
        search,
        sortBy,
        sortIndicator,
    };
}
