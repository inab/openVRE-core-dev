# Use a lightweight Python image
FROM python:3.9-slim

# Set the working directory inside the container
WORKDIR /home/

# Install Biopython for sequence handling
RUN pip install biopython

# Copy your Python script into the container
COPY extract_sequences.py /home/seqio_tool/extract_sequences.py

# Make the Python script executable
RUN chmod +x /home/seqio_tool/extract_sequences.py

# Define the entry point for the container
ENTRYPOINT ["python", "/home/seqio_tool/extract_sequences.py"]
