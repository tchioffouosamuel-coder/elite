import { useEffect } from 'react'
import { useEditor, EditorContent, type Editor } from '@tiptap/react'
import StarterKit from '@tiptap/starter-kit'
import TextAlign from '@tiptap/extension-text-align'
import { Bold, Italic, Underline as UnderlineIcon, AlignLeft, AlignCenter, AlignRight } from 'lucide-react'
import { clsx } from 'clsx'

interface RichTextEditorProps {
  label?: string
  error?: string
  value: string
  onChange: (html: string) => void
}

function ToolbarButton({
  onClick,
  active,
  children,
  title,
}: {
  onClick: () => void
  active: boolean
  children: React.ReactNode
  title: string
}) {
  return (
    <button
      type="button"
      title={title}
      // onMouseDown + preventDefault : un click normal ferait perdre le focus
      // (et donc la sélection) à l'éditeur avant que la commande ne s'applique.
      onMouseDown={(e) => {
        e.preventDefault()
        onClick()
      }}
      className={clsx(
        'flex h-7 w-7 items-center justify-center rounded-lg transition-colors',
        active ? 'bg-navy-700 text-cream-50' : 'text-navy-500 hover:bg-cream-100',
      )}
    >
      {children}
    </button>
  )
}

function Toolbar({ editor }: { editor: Editor }) {
  return (
    <div className="flex items-center gap-0.5 border-b border-navy-100 bg-cream-50/60 px-2 py-1.5">
      <ToolbarButton
        title="Gras"
        active={editor.isActive('bold')}
        onClick={() => editor.chain().focus().toggleBold().run()}
      >
        <Bold className="h-4 w-4" />
      </ToolbarButton>
      <ToolbarButton
        title="Italique"
        active={editor.isActive('italic')}
        onClick={() => editor.chain().focus().toggleItalic().run()}
      >
        <Italic className="h-4 w-4" />
      </ToolbarButton>
      <ToolbarButton
        title="Souligné"
        active={editor.isActive('underline')}
        onClick={() => editor.chain().focus().toggleUnderline().run()}
      >
        <UnderlineIcon className="h-4 w-4" />
      </ToolbarButton>

      <span className="mx-1 h-4 w-px bg-navy-100" />

      <ToolbarButton
        title="Aligner à gauche"
        active={editor.isActive({ textAlign: 'left' })}
        onClick={() => editor.chain().focus().setTextAlign('left').run()}
      >
        <AlignLeft className="h-4 w-4" />
      </ToolbarButton>
      <ToolbarButton
        title="Centrer"
        active={editor.isActive({ textAlign: 'center' })}
        onClick={() => editor.chain().focus().setTextAlign('center').run()}
      >
        <AlignCenter className="h-4 w-4" />
      </ToolbarButton>
      <ToolbarButton
        title="Aligner à droite"
        active={editor.isActive({ textAlign: 'right' })}
        onClick={() => editor.chain().focus().setTextAlign('right').run()}
      >
        <AlignRight className="h-4 w-4" />
      </ToolbarButton>
    </div>
  )
}

export function RichTextEditor({ label, error, value, onChange }: RichTextEditorProps) {
  const editor = useEditor({
    extensions: [
      StarterKit.configure({ heading: false, blockquote: false, codeBlock: false, horizontalRule: false }),
      TextAlign.configure({ types: ['paragraph'] }),
    ],
    content: value,
    onUpdate: ({ editor }) => onChange(editor.getHTML()),
    editorProps: {
      attributes: {
        class: 'min-h-[4.5rem] px-3.5 py-2.5 text-sm text-navy-900 focus:outline-none [&_p]:leading-relaxed',
      },
    },
  })

  // Resynchronise si la valeur change de l'extérieur (chargement des données,
  // reset de formulaire) ; sans la garde d'égalité, chaque frappe redéclenche
  // setContent et fait sauter le curseur.
  useEffect(() => {
    if (editor && value !== editor.getHTML()) {
      editor.commands.setContent(value || '', { emitUpdate: false })
    }
  }, [value, editor])

  return (
    <label className="flex flex-col gap-1.5">
      {label && <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">{label}</span>}
      <div
        className={clsx(
          'overflow-hidden rounded-xl border bg-white shadow-soft transition-colors focus-within:ring-4 focus-within:ring-navy-100',
          error ? 'border-red-400 focus-within:border-red-400 focus-within:ring-red-100' : 'border-navy-200 focus-within:border-navy-400',
        )}
      >
        {editor && <Toolbar editor={editor} />}
        <EditorContent editor={editor} />
      </div>
      {error && <span className="text-xs font-medium text-red-500">{error}</span>}
    </label>
  )
}
