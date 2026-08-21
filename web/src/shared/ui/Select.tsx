import ReactSelect, { type Props as ReactSelectProps } from 'react-select'

export interface SelectOption {
    value: number | string
    label: string
}

interface SelectProps extends Omit<ReactSelectProps, 'options' | 'onChange' | 'value'> {
    options: SelectOption[]
    value?: SelectOption | null
    onChange?: (option: SelectOption | null) => void
    isMulti?: false
    placeholder?: string
    isClearable?: boolean
    isSearchable?: boolean
}

const customStyles = {
    control: (base: any, state: any) => ({
        ...base,
        minHeight: '38px',
        borderColor: state.isFocused ? '#1985cc' : '#e5e7eb',
        borderRadius: '0.5rem',
        boxShadow: state.isFocused ? '0 0 0 1px #d4af37' : 'none',
        '&:hover': {
            borderColor: state.isFocused ? '#1985cc' : '#d1d5db',
        },
    }),
    option: (base: any, state: any) => ({
        ...base,
        backgroundColor: state.isSelected ? '#1985cc' : state.isFocused ? '#f3f4f6' : 'white',
        color: state.isSelected ? 'white' : '#111827',
        cursor: 'pointer',
        padding: '0.5rem 0.75rem',
        ':active': {
            backgroundColor: '#1985cc',
            color: 'white',
        },
    }),
    menu: (base: any) => ({
        ...base,
        boxShadow: '0 10px 15px -3px rgba(0, 0, 0, 0.1)',
        borderRadius: '0.5rem',
        border: '1px solid #e5e7eb',
    }),
    menuList: (base: any) => ({
        ...base,
        padding: 0,
    }),
    valueContainer: (base: any) => ({
        ...base,
        flexWrap: 'nowrap',
    }),
    singleValue: (base: any) => ({
        ...base,
        color: '#111827',
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        whiteSpace: 'nowrap',
    }),
    placeholder: (base: any) => ({
        ...base,
        color: '#9ca3af',
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        whiteSpace: 'nowrap',
    }),
    input: (base: any) => ({
        ...base,
        color: '#111827',
    }),
}

export function Select({
    options,
    value,
    onChange,
    isMulti = false,
    placeholder = 'Sélectionner...',
    isClearable = true,
    isSearchable = true,
    ...rest
}: SelectProps) {
    return (
        <ReactSelect
            options={options}
            value={value || null}
            onChange={(option) => onChange?.(option as SelectOption | null)}
            isMulti={isMulti}
            placeholder={placeholder}
            isClearable={isClearable}
            isSearchable={isSearchable}
            styles={customStyles}
            noOptionsMessage={() => 'Aucune option'}
            {...rest}
        />
    )
}
