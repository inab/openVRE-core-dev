FROM ubuntu:20.04

# Set non-interactive mode for apt-get
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update && \
    apt-get install -y wget cmake g++ make libxt6 libglu1-mesa libxrender1 \
                       python3 python3-pip libegl1 libegl1-mesa libgl1-mesa-glx \
                       software-properties-common curl libopengl0 libgl1-mesa-dri libglx0 libgl1-mesa-dev

# Add deadsnakes PPA and install Python 3.10
RUN add-apt-repository ppa:deadsnakes/ppa && \
    apt-get update && \
    apt-get install -y python3.10 python3.10-venv python3.10-dev && \
    update-alternatives --install /usr/bin/python3 python3 /usr/bin/python3.8 1 && \
    update-alternatives --install /usr/bin/python3 python3 /usr/bin/python3.10 2 && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Install Node Version Manager (NVM) and Node.js
RUN curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.3/install.sh | bash && \
    export NVM_DIR="$HOME/.nvm" && \
    [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh" && \
    nvm install 6 && \
    npm install -g pvw-visualizer

# Download and extract ParaView
RUN wget https://www.paraview.org/files/v5.12/ParaView-5.12.1-egl-MPI-Linux-Python3.10-x86_64.tar.gz && \
    tar -xvf ParaView-5.12.1-egl-MPI-Linux-Python3.10-x86_64.tar.gz && \
    rm ParaView-5.12.1-egl-MPI-Linux-Python3.10-x86_64.tar.gz

# Set the working directory
WORKDIR /ParaView-5.12.1-egl-MPI-Linux-Python3.10-x86_64

# Expose the port
EXPOSE 8090

# Command to start the ParaView visualizer
CMD ["./bin/pvpython", "-m", "paraview.apps.visualizer", "--data", "/data", "--port", "8090", "--host", "0.0.0.0"]
