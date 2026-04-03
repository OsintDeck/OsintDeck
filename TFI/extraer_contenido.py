import docx
import os

doc_path = r'C:\xampp\htdocs\osint.com.ar.local\wp-content\plugins\osint-deck\TFI\Grupo 23 - Trabajo Final de Licenciatura (TFI) Osint Deck (4).docx'
output_path = r'C:\xampp\htdocs\osint.com.ar.local\wp-content\plugins\osint-deck\TFI\contenido_tfi.txt'

def extract_text(path):
    doc = docx.Document(path)
    full_text = []
    for para in doc.paragraphs:
        full_text.append(para.text)
    return '\n'.join(full_text)

if __name__ == "__main__":
    if os.path.exists(doc_path):
        content = extract_text(doc_path)
        with open(output_path, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Contenido extraído a {output_path}")
    else:
        print(f"Error: No se encontró el archivo en {doc_path}")
