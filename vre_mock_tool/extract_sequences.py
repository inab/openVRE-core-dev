import sys
from Bio import SeqIO

def extract_fasta_sequences(fasta_file, id_file, output_file, max_length=None):
    # Read the list of IDs to extract
    with open(id_file, 'r') as f:
        target_ids = set(line.strip() for line in f)

    # Extract matching sequences from the FASTA file
    with open(fasta_file, 'r') as input_fasta, open(output_file, 'w') as output_fasta:
        for record in SeqIO.parse(input_fasta, 'fasta'):
            if record.id in target_ids:
                if max_length is None or len(record.seq) <= max_length:
                    SeqIO.write(record, output_fasta, 'fasta')

if __name__ == "__main__":
    if len(sys.argv) < 4 or len(sys.argv) > 5:
        print("Usage: python extract_sequences.py <fasta_file> <id_file> <output_file> [max_length]")
        sys.exit(1)

    fasta_file = sys.argv[1]
    id_file = sys.argv[2]
    output_file = sys.argv[3]
    max_length = int(sys.argv[4]) if len(sys.argv) == 5 else None

    extract_fasta_sequences(fasta_file, id_file, output_file, max_length)
